<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;

final class MigrateInstallCommandTest extends IntegrationTestCase
{
    /**
     * The repository table keeps track of the applied migrations, its name comes from the configuration.
     * Checks the table is created with the columns read by the Eloquent repository.
     */
    public function testCreatesTheRepositoryTable(): void
    {
        // When
        $tester = $this->runCommand('migrate:install');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Migration table created successfully.', $tester->getDisplay());
        $this->assertTrue($this->schema()->hasColumns(self::MIGRATION_TABLE, ['id', 'migration', 'batch']));
    }

    public function testUsesTheMigrationTableNameFromTheConfiguration(): void
    {
        // Given
        $this->writeConfig(options: ['migration_table' => 'custom_migrations']);

        // When
        $this->runCommand('migrate:install');

        // Then
        $this->assertTableExists('custom_migrations');
        $this->assertTableMissing(self::MIGRATION_TABLE);
    }

    public function testCreatesTheRepositoryOnTheConnectionGivenAsOption(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);

        // When
        $this->runCommand('migrate:install', ['--database' => 'secondary']);

        // Then
        $this->assertTableExists(self::MIGRATION_TABLE, 'secondary');
        $this->assertTableMissing(self::MIGRATION_TABLE, 'default');
    }

    public function testAsksForConfirmationInProduction(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);

        // When
        $tester = $this->runCommand('migrate:install');

        // Then
        $this->assertStringContainsString('Command Cancelled!', $tester->getDisplay());
        $this->assertTableMissing(self::MIGRATION_TABLE);
    }

    public function testRunsInProductionWithForce(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);

        // When
        $this->runCommand('migrate:install', ['--force' => true]);

        // Then
        $this->assertSame([], $this->questionHelper->getAskedQuestions());
        $this->assertTableExists(self::MIGRATION_TABLE);
    }
}
