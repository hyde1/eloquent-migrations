<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\Group;

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
     * Documents a known bug: without any config file, null is assigned to the typed $config property.
     * Checks the command crashes with a TypeError.
     * Expected behavior: an explicit message asks to use --config or ELMIGRATOR_CONFIG.
     */
    #[Group('documented-bug')]
    public function testFailsWithATypeErrorWhenNoConfigFileIsFound(): void
    {
        // Given
        unlink($this->workingDirectory . '/elmigrator.php');

        // Then
        $this->expectException(\TypeError::class);

        // When
        $this->runCommand('status');
    }
}
