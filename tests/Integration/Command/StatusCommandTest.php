<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;

final class StatusCommandTest extends IntegrationTestCase
{
    /**
     * Status lists every migration file with whether it ran, and in which batch.
     * Checks applied migrations show "Yes" with their batch and pending ones show "No".
     */
    public function testListsAppliedAndPendingMigrations(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');
        $this->runCommand('rollback', ['--step' => '2']);

        // When
        $tester = $this->runCommand('status');

        // Then
        $this->assertCommandSucceeded($tester);
        $display = $tester->getDisplay();
        $this->assertMatchesRegularExpression('/\|\s*Ran\?\s*\|\s*Migration\s*\|\s*Batch\s*\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s*Yes\s*\|\s*2024_01_01_000000_create_users_table\s*\|\s*1\s*\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s*Yes\s*\|\s*2024_01_02_000000_create_posts_table\s*\|\s*2\s*\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s*No\s*\|\s*2024_01_03_000000_update_users_table\s*\|/', $display);
        $this->assertMatchesRegularExpression('/\|\s*No\s*\|\s*2024_01_04_000000_insert_default_user\s*\|/', $display);
    }

    public function testReportsWhenThereIsNoMigrationFile(): void
    {
        // Given
        $this->runCommand('migrate:install');

        // When
        $tester = $this->runCommand('status');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('No migrations found', $tester->getDisplay());
    }

    public function testFailsWhenTheRepositoryIsNotInstalled(): void
    {
        // Given
        $this->useMigrations('schema');

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The migration table is not installed');

        // When
        $this->runCommand('status');
    }

    /**
     * The --database option selects the connection whose repository is read.
     * Checks the migrations applied on the secondary connection are reported as ran.
     */
    public function testReadsTheRepositoryOfTheConnectionGivenAsOption(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);
        $this->useMigrations('schema');
        $this->runCommand('migrate', ['--database' => 'secondary']);

        // When
        $tester = $this->runCommand('status', ['--database' => 'secondary']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertMatchesRegularExpression('/\|\s*Yes\s*\|\s*2024_01_04_000000_insert_default_user\s*\|\s*4\s*\|/', $tester->getDisplay());
    }
}
