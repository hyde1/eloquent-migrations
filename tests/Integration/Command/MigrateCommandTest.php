<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\Group;

final class MigrateCommandTest extends IntegrationTestCase
{
    /**
     * Nominal run on an empty database: the migration repository does not exist yet.
     * Checks the repository is created on the fly, every migration is applied and recorded.
     */
    public function testInstallsTheRepositoryAndRunsAllPendingMigrations(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists(self::MIGRATION_TABLE);
        $this->assertTableExists('users');
        $this->assertTableExists('posts');
        $this->assertSame(
            ['id', 'email', 'full_name', 'active', 'settings', 'last_login_at', 'created_at', 'updated_at', 'deleted_at', 'phone'],
            $this->schema()->getColumnListing('users'),
        );
        $this->assertSame(1, $this->db()->table('users')->where('email', 'admin@example.com')->count());
        $this->assertStringContainsString('2024_01_04_000000_insert_default_user', $tester->getDisplay());
    }

    /**
     * The --step option defaults to 1, so each migration gets its own batch and can be rolled back individually.
     * Checks the batch numbers recorded in the repository.
     */
    public function testRecordsEachMigrationInItsOwnBatchByDefault(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $this->runCommand('migrate');

        // Then
        $this->assertSame([
            '2024_01_01_000000_create_users_table' => 1,
            '2024_01_02_000000_create_posts_table' => 2,
            '2024_01_03_000000_update_users_table' => 3,
            '2024_01_04_000000_insert_default_user' => 4,
        ], $this->ranMigrations());
    }

    public function testRecordsAllMigrationsInTheSameBatchWithoutStep(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $this->runCommand('migrate', ['--step' => '0']);

        // Then
        $this->assertSame([1], array_values(array_unique($this->ranMigrations())));
    }

    /**
     * Migrations already recorded in the repository must never be applied twice.
     * Checks a second run only applies the migrations added since the first one.
     */
    public function testOnlyRunsMigrationsThatWereNotAlreadyApplied(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');
        $this->useMigrations('connection');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertCount(5, $this->ranMigrations());
        $this->assertSame(5, $this->ranMigrations()['2024_03_01_000000_create_audit_logs_table']);
    }

    public function testReportsThatThereIsNothingToMigrate(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Nothing to migrate', $tester->getDisplay());
        $this->assertCount(4, $this->ranMigrations());
    }

    /**
     * The --dry-run option relies on Eloquent "pretend" mode, used to review the SQL before a production deployment.
     * Checks the SQL queries are printed while the schema and the repository stay untouched.
     */
    public function testDryRunPrintsTheQueriesWithoutExecutingThem(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate', ['--dry-run' => true]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertMatchesRegularExpression('/create table ["`]?users["`]?/i', $tester->getDisplay());
        $this->assertTableMissing('users');
        $this->assertSame([], $this->ranMigrations());
    }

    /**
     * Migrations declaring a $connection property run on that connection, whatever the default connection is.
     * Checks the table is created on the declared connection only (sqlite uses one file per connection).
     */
    public function testRunsMigrationsOnTheConnectionTheyDeclare(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);
        $this->useMigrations('connection');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('audit_logs', 'secondary');
        $this->assertTableMissing('audit_logs', 'default');
        $this->assertArrayHasKey('2024_03_01_000000_create_audit_logs_table', $this->ranMigrations('default'));
    }

    /**
     * The --database option selects the connection used for both the repository and the migrations.
     * Checks nothing is written on the default connection.
     */
    public function testRunsMigrationsOnTheConnectionGivenAsOption(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate', ['--database' => 'secondary']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTableExists('users', 'secondary');
        $this->assertCount(4, $this->ranMigrations('secondary'));
        $this->assertTableMissing('users', 'default');
        $this->assertTableMissing(self::MIGRATION_TABLE, 'default');
    }

    /**
     * A failing migration must stop the run and must not be recorded, so it is retried on the next run.
     * Checks the error is propagated and the repository does not contain the failing migration.
     */
    public function testStopsAndDoesNotRecordAFailingMigration(): void
    {
        // Given
        $this->useMigrations('failing');

        // When
        try {
            $this->runCommand('migrate');
            $this->fail('The migration error should have been propagated');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Migration failed on purpose', $exception->getMessage());
        }

        // Then
        $this->assertSame([], $this->ranMigrations());
    }

    /**
     * PostgreSQL supports transactional DDL, so Eloquent wraps each migration in a transaction.
     * Checks the table created by a failing migration is rolled back with it.
     */
    public function testRollsBackTheSchemaChangesOfAFailingMigrationRunWithinATransaction(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::PGSQL);
        $this->useMigrations('failing');

        // When
        $this->runFailingMigrate();

        // Then
        $this->assertTableMissing('transactional_table');
    }

    /**
     * Migrations can opt out of the transaction with "public $withinTransaction = false;" (e.g. CREATE INDEX CONCURRENTLY).
     * Checks the changes made before the failure are kept, proving the migration did not run inside a transaction.
     */
    public function testKeepsTheSchemaChangesOfAFailingMigrationRunWithoutTransaction(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::PGSQL);
        $this->useMigrations('failing_without_transaction');

        // When
        $this->runFailingMigrate();

        // Then
        $this->assertTableExists('non_transactional_table');
    }

    /**
     * Running migrations in production asks for a confirmation, unless --force is given.
     * Checks the run is cancelled without touching the database when the question is not answered (e.g. in a CI).
     */
    public function testAsksForConfirmationInProduction(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Command Cancelled!', $tester->getDisplay());
        $this->assertTableMissing(self::MIGRATION_TABLE);
        $this->assertTableMissing('users');
    }

    public function testRunsInProductionWhenTheConfirmationIsAccepted(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);
        $this->useMigrations('schema');
        // Avoids the migrate:install confirmation, see testForceIsNotForwardedToTheRepositoryInstallationInProduction()
        $this->runCommand('migrate:install', ['--force' => true]);

        // When
        $tester = $this->runCommand('migrate', [], ['yes']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame(['Do you really wish to run this command?'], $this->questionHelper->getAskedQuestions());
        $this->assertCount(4, $this->ranMigrations());
    }

    public function testEnvOptionOverridesTheConfiguredEnvironment(): void
    {
        // Given
        $this->useMigrations('schema');

        // When
        $tester = $this->runCommand('migrate', ['--env' => 'production']);

        // Then
        $this->assertStringContainsString('Command Cancelled!', $tester->getDisplay());
        $this->assertTableMissing('users');
    }

    /**
     * Documents a known bug: migrate calls migrate:install without forwarding --force, so the sub-command asks for
     * its own confirmation in production. Checks a non-interactive "migrate --force" on a fresh production database
     * fails because the repository table was not created.
     * Expected behavior: --force also applies to the repository creation and the migrations run.
     */
    #[Group('documented-bug')]
    public function testForceIsNotForwardedToTheRepositoryInstallationInProduction(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);
        $this->useMigrations('schema');

        // When
        try {
            $this->runCommand('migrate', ['--force' => true]);
            $this->fail('The missing repository table should have made the command fail');
        } catch (\Illuminate\Database\QueryException) {
        }

        // Then
        $this->assertTableMissing(self::MIGRATION_TABLE);
        $this->assertTableMissing('users');
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function runFailingMigrate(): void
    {
        try {
            $this->runCommand('migrate');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Migration failed on purpose', $exception->getMessage());

            return;
        }

        $this->fail('The migration error should have been propagated');
    }
}
