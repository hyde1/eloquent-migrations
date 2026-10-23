<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Group;

final class FreshMigrationCommandTest extends IntegrationTestCase
{
    /**
     * migrate:fresh rebuilds a database from scratch (used to prepare test databases): every table is dropped,
     * including tables not created by a migration, then all migrations run again.
     * Checks the repository restarts from the first batch and the unknown table is gone.
     */
    public function testDropsAllTablesAndRunsAllMigrationsAgain(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');
        $this->runCommand('rollback');
        $this->schema()->create('not_a_migration', function (Blueprint $table): void {
            $table->increments('id');
        });

        // When
        $tester = $this->runCommand('migrate:fresh');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Dropped all tables successfully.', $tester->getDisplay());
        $this->assertTableMissing('not_a_migration');
        $this->assertCount(4, $this->ranMigrations());
        $this->assertSame(1, $this->ranMigrations()['2024_01_01_000000_create_users_table']);
        $this->assertSame(1, $this->db()->table('users')->count());
    }

    public function testDropsViewsWhenRequested(): void
    {
        // Given
        $this->useMigrations('schema');
        $this->runCommand('migrate');
        $this->db()->statement('create view active_users as select * from users where active = true');

        // When
        $tester = $this->runCommand('migrate:fresh', ['--drop-views' => true]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Dropped all views successfully.', $tester->getDisplay());
        $this->assertSame([], $this->schema()->getViews());
        $this->assertCount(4, $this->ranMigrations());
    }

    /**
     * The --seed option chains seed:run after the migrations, like "migrate:fresh --seed" used by the BO CI.
     * Checks every seeder ran, in dependency order.
     */
    public function testRunsTheSeedersWhenRequested(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);
        $this->useMigrations('seed_runs');

        // When
        $tester = $this->runCommand('migrate:fresh', ['--seed' => true]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame(['user', 'post', 'comment'], $this->db()->table('seed_runs')->orderBy('id')->pluck('name')->all());
    }

    /**
     * Documents a known bug: --seeder is forwarded to seed:run as a string while its --seed option expects an array.
     * Checks a PHP warning is raised and no seeder runs at all.
     * Expected behavior: only the given seeder runs.
     */
    #[Group('documented-bug')]
    public function testSeederOptionDoesNotRunTheGivenSeeder(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);
        $this->useMigrations('seed_runs');

        // When
        $warnings = $this->captureWarnings(fn () => $this->runCommand('migrate:fresh', ['--seed' => true, '--seeder' => 'Database\Seeders\PostSeeder']));

        // Then
        $this->assertSame(['foreach() argument must be of type array|object, string given'], $warnings);
        $this->assertSame([], $this->db()->table('seed_runs')->pluck('name')->all());
    }

    /**
     * Dropping every table in production is destructive: a confirmation is required unless --force is given.
     * Checks nothing is dropped when the confirmation is not given, and the command exits with an error code.
     */
    public function testAsksForConfirmationInProduction(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production']);
        $this->useMigrations('schema');
        $this->runCommand('migrate', ['--force' => true], ['yes']);

        // When
        $tester = $this->runCommand('migrate:fresh');

        // Then
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Command Cancelled!', $tester->getDisplay());
        $this->assertSame(1, $this->db()->table('users')->count());
    }

    /**
     * --force must apply to the whole rebuild, including the migrate and seed:run sub-commands.
     * Checks the database is rebuilt in production without any question.
     */
    public function testRunsInProductionWithForce(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production', 'seeds' => self::seedersFixturePath('dependencies')]);
        $this->useMigrations('seed_runs');
        $this->runCommand('migrate', ['--force' => true]);

        // When
        $tester = $this->runCommand('migrate:fresh', ['--force' => true, '--seed' => true]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame([], $this->questionHelper->getAskedQuestions());
        $this->assertCount(1, $this->ranMigrations());
        $this->assertSame(['user', 'post', 'comment'], $this->db()->table('seed_runs')->orderBy('id')->pluck('name')->all());
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * @return list<string> Messages of the PHP warnings raised by the callback
     */
    private function captureWarnings(callable $callback): array
    {
        $warnings = [];
        set_error_handler(function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_WARNING);

        try {
            $callback();
        } finally {
            restore_error_handler();
        }

        return $warnings;
    }
}
