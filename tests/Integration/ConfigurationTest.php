<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration;

use Hyde1\EloquentMigrations\Command\AbstractCommand;
use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\ApplicationTester;

/**
 * Projects can have several config files (e.g. elmigrator.php and elmigrator-bms.php in the BO), selected with
 * --config or the ELMIGRATOR_CONFIG environment variable. The file is resolved from the current working directory.
 */
final class ConfigurationTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['ELMIGRATOR_CONFIG']);

        parent::tearDown();
    }

    /**
     * Commands calling sub-commands (migrate calls migrate:install) must forward the selected config file.
     * Checks the repository is created with the migration table of the given config file, not the default one.
     */
    public function testConfigOptionIsUsedByTheCommandAndItsSubCommands(): void
    {
        // Given
        $this->writeConfig('elmigrator-other.php', ['migration_table' => 'other_migrations']);
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate', ['--config' => 'elmigrator-other.php']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('other_migrations');
        $this->assertTableMissing(self::MIGRATION_TABLE);
        $this->assertSame(4, $this->db()->table('other_migrations')->count());
    }

    public function testConfigOptionAcceptsARelativePath(): void
    {
        // Given
        mkdir($this->workingDirectory . '/config');
        $this->writeConfig('config/elmigrator.php', ['migration_table' => 'other_migrations']);
        unlink($this->workingDirectory . '/elmigrator.php');

        // When
        $tester = $this->runCommand('migrate:install', ['-c' => 'config/elmigrator.php']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('other_migrations');
    }

    /**
     * The README documents ELMIGRATOR_CONFIG for projects that keep their config file outside of the root directory.
     * Checks it is used when the --config file does not exist.
     */
    public function testUsesTheConfigFileFromTheEnvironmentWhenTheDefaultFileIsMissing(): void
    {
        // Given
        mkdir($this->workingDirectory . '/config');
        $this->writeConfig('config/elmigrator.php', ['migration_table' => 'env_migrations']);
        unlink($this->workingDirectory . '/elmigrator.php');
        $_ENV['ELMIGRATOR_CONFIG'] = 'config/elmigrator.php';

        // When
        $tester = $this->runCommand('migrate:install');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('env_migrations');
    }

    public function testMigrationsPathIsReadFromTheConfiguration(): void
    {
        // Given
        $this->writeConfig(options: ['migrations' => 'db/migrations']);
        mkdir($this->workingDirectory . '/db/migrations', 0o777, true);
        $this->runCommand('migrate:install');

        // When
        $tester = $this->runCommand('create', ['name' => 'create_widgets_table']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertCount(1, glob($this->workingDirectory . '/db/migrations/*_create_widgets_table.php') ?: []);
        $this->assertSame([], glob($this->migrationsDirectory() . '/*.php'));
    }

    /**
     * Commands are bootstrapped by AbstractCommand::initialize(). Commands written before, extending AbstractCommand
     * outside of this library, still call bootstrap() in execute(). Checks they keep working and the config file is
     * required once, as it may have side effects (e.g. Capsule::setAsGlobal()).
     */
    public function testCommandsCallingBootstrapThemselvesLoadTheConfigOnce(): void
    {
        // Given
        $configFile = $this->workingDirectory . '/elmigrator.php';
        file_put_contents($configFile, str_replace(
            '<?php',
            "<?php\n\$GLOBALS['elmigrator_config_loads'] = (\$GLOBALS['elmigrator_config_loads'] ?? 0) + 1;",
            (string) file_get_contents($configFile),
        ));
        $GLOBALS['elmigrator_config_loads'] = 0;

        /** @var Application $application */
        $application = require __DIR__ . '/../../app/elmigrator.php';
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);
        $application->addCommands([new class ('custom') extends AbstractCommand {
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $this->bootstrap($input, $output);
                $output->writeln('Migration table: ' . $this->getMigrationTable());

                return self::SUCCESS;
            }
        }]);
        $tester = new ApplicationTester($application);

        // When
        $tester->run(['command' => 'custom']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Migration table: ' . self::MIGRATION_TABLE, $tester->getDisplay());
        $this->assertSame(1, $GLOBALS['elmigrator_config_loads']);
        unset($GLOBALS['elmigrator_config_loads']);
    }

    /**
     * A command instance can be run several times with the same input and output objects (workers, tests).
     * Checks each run loads the configuration again, so a changed config file is taken into account.
     */
    public function testEachRunOfTheSameCommandLoadsTheConfiguration(): void
    {
        // Given
        /** @var Application $application */
        $application = require __DIR__ . '/../../app/elmigrator.php';
        $command = $application->find('migrate:install');
        $input = new ArrayInput([]);
        $output = new BufferedOutput();
        $command->run($input, $output);
        $this->writeConfig(options: ['migration_table' => 'other_migrations']);

        // When
        $command->run($input, $output);

        // Then
        $this->assertTableExists('other_migrations');
    }

    public function testFailsWithAnExplicitMessageWhenNoConfigFileIsFound(): void
    {
        // Given
        unlink($this->workingDirectory . '/elmigrator.php');

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not find the configuration file "elmigrator.php". Set it with the --config option or the ELMIGRATOR_CONFIG environment variable.');

        // When
        $this->runCommand('status');
    }

    public function testFailsWhenTheConfigFileDoesNotReturnAnArray(): void
    {
        // Given
        file_put_contents($this->workingDirectory . '/elmigrator.php', '<?php // returns nothing');

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('elmigrator.php" must return an array');

        // When
        $this->runCommand('status');
    }
}
