<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\Group;

final class RollbackCommandTest extends IntegrationTestCase
{
    /**
     * Without option, the rollback reverts the last batch (one migration, as migrate uses --step=1 by default).
     * Checks the down() method of the last migration ran and the migration is removed from the repository.
     */
    public function testRollsBackTheLastBatch(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate', ['--step' => '0']);
        $this->runCommand('migrate'); // nothing to migrate, does not create an empty batch

        // When
        $tester = $this->runCommand('rollback');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame([], $this->ranMigrations());
        $this->assertTableMissing('users');
        $this->assertTableMissing('posts');
        $this->assertTableExists(self::MIGRATION_TABLE);
    }

    public function testRollsBackOnlyTheLastMigrationWhenMigratedStepByStep(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $tester = $this->runCommand('rollback');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertArrayNotHasKey('2024_01_04_000000_insert_default_user', $this->ranMigrations());
        $this->assertCount(3, $this->ranMigrations());
        $this->assertSame(0, $this->db()->table('users')->count());
        $this->assertStringContainsString('2024_01_04_000000_insert_default_user', $tester->getDisplay());
    }

    /**
     * The down() methods revert schema changes in reverse order: renamed and dropped columns are restored.
     * Checks the users table is back to its initial structure after rolling back the update migration.
     */
    public function testRollsBackTheGivenNumberOfSteps(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $tester = $this->runCommand('rollback', ['--step' => '2']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame([
            '2024_01_01_000000_create_users_table' => 1,
            '2024_01_02_000000_create_posts_table' => 2,
        ], $this->ranMigrations());
        $this->assertTrue($this->schema()->hasColumns('users', ['name', 'age']));
        $this->assertFalse($this->schema()->hasColumn('users', 'full_name'));
        $this->assertFalse($this->schema()->hasColumn('users', 'phone'));
    }

    public function testRollsBackEverythingWithAStepGreaterThanTheNumberOfMigrations(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $this->runCommand('rollback', ['--step' => '10']);

        // Then
        $this->assertSame([], $this->ranMigrations());
        $this->assertTableMissing('users');
    }

    public function testDryRunPrintsTheQueriesWithoutExecutingThem(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $tester = $this->runCommand('rollback', ['--dry-run' => true, '--step' => '4']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertMatchesRegularExpression('/drop table if exists ["`]?posts["`]?/i', $tester->getDisplay());
        $this->assertCount(4, $this->ranMigrations());
        $this->assertTableExists('posts');
    }

    public function testReportsThatThereIsNothingToRollback(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate:install');

        // When
        $tester = $this->runCommand('rollback');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Nothing to rollback', $tester->getDisplay());
    }

    /**
     * The --database option must be honored by the rollback too (regression fixed in 3574b3b).
     * Checks the migrations recorded on the secondary connection are rolled back there.
     */
    public function testRollsBackOnTheConnectionGivenAsOption(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);
        $this->useMigrations('schema');
        $this->runCommand('migrate', ['--database' => 'secondary']);

        // When
        $tester = $this->runCommand('rollback', ['--database' => 'secondary', '--step' => '4']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame([], $this->ranMigrations('secondary'));
        $this->assertTableMissing('users', 'secondary');
    }

    /**
     * Documents a known bug: the --migration option is not supported by the Eloquent Migrator, which ignores it.
     * Checks asking to rollback the first migration rolls back the last batch instead.
     * Expected behavior: only the given migration is rolled back, or the option is removed.
     */
    #[Group('documented-bug')]
    public function testMigrationOptionIsIgnored(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $this->runCommand('rollback', ['--migration' => '2024_01_01_000000_create_users_table']);

        // Then
        $this->assertArrayHasKey('2024_01_01_000000_create_users_table', $this->ranMigrations());
        $this->assertArrayNotHasKey('2024_01_04_000000_insert_default_user', $this->ranMigrations());
    }
}
