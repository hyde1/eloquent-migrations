<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;

/**
 * Smoke test of the real binary, run in separate processes like in a project using the library.
 */
final class EndToEndTest extends IntegrationTestCase
{
    /**
     * Typical lifecycle of a project: generate a migration, migrate, check the status, rollback.
     * Checks each step through bin/elmigrator exits successfully and leaves the database in the expected state.
     */
    public function testMigrationLifecycleThroughTheBinary(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $create = $this->runBinary('create', 'add_nickname_to_users_table');
        $migrate = $this->runBinary('migrate');
        $statusAfterMigrate = $this->runBinary('status');
        $rollback = $this->runBinary('rollback', '--step=5');
        $statusAfterRollback = $this->runBinary('status');

        // Then
        foreach ([$create, $migrate, $statusAfterMigrate, $rollback, $statusAfterRollback] as $step) {
            $this->assertSame(0, $step['exitCode'], $step['output']);
        }
        $this->assertStringContainsString('Created Migration:', $create['output']);
        $this->assertMatchesRegularExpression('/\|\s*Yes\s*\|\s*\d{4}_\d{2}_\d{2}_\d{6}_add_nickname_to_users_table\s*\|\s*5\s*\|/', $statusAfterMigrate['output']);
        $this->assertMatchesRegularExpression('/\|\s*No\s*\|\s*2024_01_01_000000_create_users_table\s*\|/', $statusAfterRollback['output']);
        $this->assertSame([], $this->ranMigrations());
        $this->assertTableMissing('users');
    }
}
