<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support;

use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Runs the elmigrator console application inside an isolated working directory, against a real
 * database, exactly like a project using the library would (config file required from the cwd).
 */
abstract class IntegrationTestCase extends TestCase
{
    protected const string MIGRATION_TABLE = 'el_migrations';
    protected const string MIGRATIONS_PATH = 'database/migrations';
    protected const string SEEDS_PATH = 'database/seeders';

    private const string APPLICATION_FILE = __DIR__ . '/../../app/elmigrator.php';
    private const string BIN_FILE = __DIR__ . '/../../bin/elmigrator';
    private const string FIXTURES_PATH = __DIR__ . '/../Fixtures';

    protected string $workingDirectory;
    protected ScriptedQuestionHelper $questionHelper;
    private string $previousWorkingDirectory;
    private Manager $capsule;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousWorkingDirectory = (string) getcwd();
        $this->workingDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'elmigrator-tests-' . bin2hex(random_bytes(8));
        mkdir($this->workingDirectory . DIRECTORY_SEPARATOR . self::MIGRATIONS_PATH, 0o777, true);
        mkdir($this->workingDirectory . DIRECTORY_SEPARATOR . self::SEEDS_PATH, 0o777, true);
        chdir($this->workingDirectory);

        $this->capsule = TestDatabase::createCapsule($this->workingDirectory, ['default', 'secondary']);
        $this->resetDatabase();
        $this->writeConfig();
    }

    protected function tearDown(): void
    {
        if (!TestDatabase::isSqlite()) {
            $this->resetDatabase();
        }
        $this->capsule->getDatabaseManager()->disconnect('default');
        $this->capsule->getDatabaseManager()->disconnect('secondary');

        chdir($this->previousWorkingDirectory);
        new Filesystem()->deleteDirectory($this->workingDirectory);

        parent::tearDown();
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * Writes a config file in the working directory, shaped like data/elmigrator.php.dist.
     *
     * @param array{environment?: string, seeds?: string, migrations?: string, migration_table?: string} $options
     */
    protected function writeConfig(string $fileName = 'elmigrator.php', array $options = []): void
    {
        $connections = '';
        foreach (['default', 'secondary'] as $name) {
            $connections .= sprintf(
                "\$capsule->addConnection(%s, %s);\n",
                var_export(TestDatabase::connection($this->workingDirectory, $name), true),
                var_export($name, true),
            );
        }
        // The template below goes through sprintf()
        $connections = str_replace('%', '%%', $connections);

        $config = <<<PHP
            <?php

            \$capsule = new \\Illuminate\\Database\\Capsule\\Manager();
            {$connections}
            return [
                'default_environment' => %s,
                'paths' => [
                    'migrations' => %s,
                    'seeds' => %s,
                ],
                'migration_table' => %s,
                'db' => \$capsule->getDatabaseManager(),
            ];

            PHP;

        file_put_contents($this->workingDirectory . DIRECTORY_SEPARATOR . $fileName, sprintf(
            $config,
            var_export($options['environment'] ?? 'test', true),
            var_export($options['migrations'] ?? self::MIGRATIONS_PATH, true),
            var_export($options['seeds'] ?? self::SEEDS_PATH, true),
            var_export($options['migration_table'] ?? self::MIGRATION_TABLE, true),
        ));
    }

    /**
     * Runs a command through a fresh console application, the same way bin/elmigrator does.
     * Exceptions are not caught so that a failing test shows the real error and its stack trace.
     *
     * @param array<string, mixed> $parameters
     * @param list<string> $answers Answers to the questions asked by the command and its sub-commands, in order.
     *                              Unanswered questions get their default value, as when STDIN is closed.
     */
    protected function runCommand(string $command, array $parameters = [], array $answers = []): ApplicationTester
    {
        /** @var Application $application */
        $application = require self::APPLICATION_FILE;
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $this->questionHelper = new ScriptedQuestionHelper($answers);
        $application->getHelperSet()->set($this->questionHelper, 'question');

        $tester = new ApplicationTester($application);
        $tester->run(['command' => $command] + $parameters, ['decorated' => false]);

        return $tester;
    }

    /**
     * Runs the real bin/elmigrator in a separate process, from the working directory.
     *
     * @return array{exitCode: int, output: string}
     */
    protected function runBinary(string ...$arguments): array
    {
        return $this->runPhp(self::BIN_FILE, ...$arguments);
    }

    /**
     * Loads a generated PHP file in a separate process, see tests/Support/inspect-php-file.php.
     *
     * @return array{exitCode: int, output: string, returns_migration?: bool, declared_seeders?: list<string>}
     */
    protected function inspectPhpFile(string $file): array
    {
        $result = $this->runPhp(__DIR__ . '/inspect-php-file.php', $file);
        if ($result['exitCode'] !== 0) {
            return $result;
        }

        return $result + json_decode($result['output'], true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * Copies migration fixtures (tests/Fixtures/migrations/<scenario>) into the project migrations path.
     */
    protected function useMigrations(string ...$scenarios): void
    {
        foreach ($scenarios as $scenario) {
            $files = glob(self::FIXTURES_PATH . '/migrations/' . $scenario . '/*.php') ?: [];
            self::assertNotEmpty($files, sprintf('No migration fixture found for scenario "%s"', $scenario));

            foreach ($files as $file) {
                copy($file, $this->migrationsDirectory() . DIRECTORY_SEPARATOR . basename($file));
            }
        }
    }

    /**
     * Seeder classes are loaded with require_once, so fixtures are used in place instead of being
     * copied: each class must keep a single file path for the whole test run.
     */
    protected static function seedersFixturePath(string $scenario): string
    {
        return (string) realpath(self::FIXTURES_PATH . '/seeders/' . $scenario);
    }

    protected function migrationsDirectory(): string
    {
        return $this->workingDirectory . DIRECTORY_SEPARATOR . self::MIGRATIONS_PATH;
    }

    protected function seedsDirectory(): string
    {
        return $this->workingDirectory . DIRECTORY_SEPARATOR . self::SEEDS_PATH;
    }

    /**
     * Connection used to inspect the database state from the tests.
     * SQLite connections are reopened on each call, as the commands may have recreated the file.
     */
    protected function db(string $connection = 'default'): Connection
    {
        $manager = $this->capsule->getDatabaseManager();
        if (TestDatabase::isSqlite()) {
            $manager->purge($connection);
        }

        return $manager->connection($connection);
    }

    protected function schema(string $connection = 'default'): Builder
    {
        return $this->db($connection)->getSchemaBuilder();
    }

    /**
     * @return array<string, int> Batch number indexed by migration name, in execution order
     */
    protected function ranMigrations(string $connection = 'default'): array
    {
        if (!$this->schema($connection)->hasTable(self::MIGRATION_TABLE)) {
            return [];
        }

        return $this->db($connection)
            ->table(self::MIGRATION_TABLE)
            ->orderBy('id')
            ->pluck('batch', 'migration')
            ->map(fn (mixed $batch): int => (int) $batch)
            ->all();
    }

    protected function skipUnlessDriver(string ...$drivers): void
    {
        if (!in_array(TestDatabase::driver(), $drivers, true)) {
            $this->markTestSkipped(sprintf('Only relevant with %s (current: %s)', implode(', ', $drivers), TestDatabase::driver()));
        }
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function runPhp(string $script, string ...$arguments): array
    {
        $command = implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, $script, ...$arguments]));
        exec($command . ' 2>&1', $output, $exitCode);

        return ['exitCode' => $exitCode, 'output' => implode(PHP_EOL, $output)];
    }

    private function resetDatabase(): void
    {
        foreach (TestDatabase::isSqlite() ? ['default', 'secondary'] : ['default'] as $connection) {
            $schema = $this->schema($connection);
            $schema->dropAllViews();
            $schema->dropAllTables();
        }
    }

    // -------------------
    // 🧵 Custom Assertions
    // -------------------

    protected function assertTableExists(string $table, string $connection = 'default'): void
    {
        $this->assertTrue($this->schema($connection)->hasTable($table), sprintf('Table "%s" should exist on connection "%s"', $table, $connection));
    }

    protected function assertTableMissing(string $table, string $connection = 'default'): void
    {
        $this->assertFalse($this->schema($connection)->hasTable($table), sprintf('Table "%s" should not exist on connection "%s"', $table, $connection));
    }

    protected function assertCommandSucceeded(ApplicationTester $tester): void
    {
        $this->assertSame(0, $tester->getStatusCode(), 'Command failed with output: ' . $tester->getDisplay());
    }
}
