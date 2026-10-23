<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;

/**
 * Projects can have several config files (e.g. elmigrator.php and elmigrator-bms.php in the BO), selected with
 * --config or the ELMIGRATOR_CONFIG environment variable. The file is resolved from the current working directory.
 */
final class ConfigurationTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        unset($_ENV['ELMIGRATOR_CONFIG']);
        putenv('ELMIGRATOR_CONFIG');

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

    /**
     * With the default variables_order (GPCS), $_ENV is empty: a variable exported in the shell, as the README
     * documents, is only available through getenv().
     */
    public function testUsesTheConfigFileFromAnExportedEnvironmentVariable(): void
    {
        // Given
        mkdir($this->workingDirectory . '/config');
        $this->writeConfig('config/elmigrator.php', ['migration_table' => 'env_migrations']);
        unlink($this->workingDirectory . '/elmigrator.php');
        putenv('ELMIGRATOR_CONFIG=config/elmigrator.php');

        // When
        $tester = $this->runCommand('migrate:install');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('env_migrations');
    }

    public function testConfigOptionAcceptsAnAbsolutePath(): void
    {
        // Given
        mkdir($this->workingDirectory . '/config');
        $this->writeConfig('config/elmigrator.php', ['migration_table' => 'other_migrations']);
        unlink($this->workingDirectory . '/elmigrator.php');

        // When
        $tester = $this->runCommand('migrate:install', ['--config' => $this->workingDirectory . '/config/elmigrator.php']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('other_migrations');
    }

    /**
     * Commands calling sub-commands forward the config file as given, the absolute path must work for them too.
     */
    public function testEnvironmentVariableAcceptsAnAbsolutePath(): void
    {
        // Given
        mkdir($this->workingDirectory . '/config');
        $this->writeConfig('config/elmigrator.php', ['migration_table' => 'env_migrations']);
        unlink($this->workingDirectory . '/elmigrator.php');
        putenv('ELMIGRATOR_CONFIG=' . $this->workingDirectory . '/config/elmigrator.php');
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame(4, $this->db()->table('env_migrations')->count());
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
