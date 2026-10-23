<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * The create command relies on the Eloquent MigrationCreator and on the stubs shipped in data/stubs.
 */
final class CreateMigrationCommandTest extends IntegrationTestCase
{
    /**
     * The stub is chosen from the options, or guessed by Eloquent from the migration name (create_x_table, x_to_y_table...).
     * Checks the generated file name and that the table placeholder is replaced in the expected stub.
     *
     * @param array<string, string> $options
     */
    #[DataProvider('migrationsProvider')]
    public function testGeneratesAMigrationFromTheMatchingStub(string $name, array $options, string $expectedFileSuffix, string $expectedCode): void
    {
        // When
        $tester = $this->runCommand('create', ['name' => $name] + $options);

        // Then
        $this->assertCommandSucceeded($tester);
        $file = $this->generatedMigration();
        $this->assertMatchesRegularExpression('/^\d{4}_\d{2}_\d{2}_\d{6}_' . preg_quote($expectedFileSuffix, '/') . '$/', basename($file));
        $this->assertStringContainsString('Created Migration:', $tester->getDisplay());

        $code = (string) file_get_contents($file);
        $this->assertStringContainsString($expectedCode, $code);
        $this->assertStringNotContainsString('{{', $code, 'Every stub placeholder should be replaced');
    }

    /**
     * Generated migrations are anonymous classes returned by the file, resolved by the Eloquent Migrator.
     * Checks every kind of generated file can be loaded and returns a library Migration.
     */
    #[DataProvider('stubsProvider')]
    public function testGeneratedMigrationIsALoadableMigration(string $name, array $options): void
    {
        // Given
        $this->runCommand('create', ['name' => $name] + $options);

        // When
        $inspection = $this->inspectPhpFile($this->generatedMigration());

        // Then
        $this->assertSame(0, $inspection['exitCode'], $inspection['output']);
        $this->assertTrue($inspection['returns_migration'] ?? false);
    }

    /**
     * End-to-end check of the update stub: generated, applied then rolled back without editing them.
     * Checks the migration is recorded then removed from the repository.
     */
    #[DataProvider('runnableMigrationsProvider')]
    public function testGeneratedMigrationCanBeMigratedAndRolledBack(string $name, array $options): void
    {
        // Given
        $this->useMigrations('seed_runs');
        $this->runCommand('create', ['name' => $name] + $options);
        $migration = basename($this->generatedMigration(), '.php');

        // When
        $this->runCommand('migrate');
        $ranAfterMigrate = $this->ranMigrations();
        $this->runCommand('rollback');

        // Then
        $this->assertArrayHasKey($migration, $ranAfterMigrate);
        $this->assertArrayNotHasKey($migration, $this->ranMigrations());
    }

    /**
     * Documents a known bug: the create stub calls $table->primaryKey('id'), which is not a Blueprint method.
     * Checks migrating a freshly generated "create" migration fails.
     * Expected behavior: the generated migration creates the table ($table->primary('id')).
     */
    #[Group('documented-bug')]
    public function testGeneratedCreateMigrationCannotBeMigrated(): void
    {
        // Given
        $this->runCommand('create', ['name' => 'create_orders_table']);

        // Then
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('primaryKey');

        // When
        $this->runCommand('migrate');
    }

    /**
     * Documents a known bug: the stubs suggest uncommenting "public bool $withinTransaction = false;", but the
     * Eloquent parent property is untyped, so redeclaring it with a type is a fatal error.
     * Checks the generated migration cannot be loaded once the line is uncommented.
     * Expected behavior: the hint is "public $withinTransaction = false;", which loads fine.
     */
    #[Group('documented-bug')]
    public function testUncommentingTheWithinTransactionHintBreaksTheMigration(): void
    {
        // Given
        $this->runCommand('create', ['name' => 'add_status_to_orders_table']);
        $file = $this->generatedMigration();
        file_put_contents($file, str_replace('//public bool $withinTransaction', 'public bool $withinTransaction', (string) file_get_contents($file)));

        // When
        $inspection = $this->inspectPhpFile($file);

        // Then
        $this->assertNotSame(0, $inspection['exitCode']);
        // The message differs between PHP versions
        $this->assertMatchesRegularExpression('/Type of .*::\$withinTransaction must (not be defined|be omitted)/', $inspection['output']);
    }

    /**
     * Documents a known bug: when no table is given nor guessed from the name, Eloquent returns a null table which
     * is passed to a string parameter. Checks generating a blank migration fails with a TypeError.
     * Expected behavior: a migration is generated from the blank stub (data/stubs/migration.stub).
     */
    #[Group('documented-bug')]
    public function testCannotGenerateABlankMigration(): void
    {
        // Then
        $this->expectException(\TypeError::class);

        // When
        try {
            $this->runCommand('create', ['name' => 'backfill_data']);
        } finally {
            $this->assertSame([], glob($this->migrationsDirectory() . '/*.php'));
        }
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function generatedMigration(): string
    {
        $files = glob($this->migrationsDirectory() . '/*.php') ?: [];
        $files = array_values(array_filter($files, fn (string $file): bool => !str_ends_with($file, '_create_seed_runs_table.php')));
        $this->assertCount(1, $files, 'Exactly one migration should have been generated');

        return $files[0];
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{string, array<string, string>, string, string}> */
    public static function migrationsProvider(): array
    {
        return [
            'create guessed from the name' => ['CreateUsersTable', [], 'create_users_table.php', "\$this->schema()->create('users'"],
            'create option' => ['build_orders', ['--create' => 'orders'], 'build_orders.php', "\$this->schema()->create('orders'"],
            'update guessed from the name' => ['add_status_to_orders_table', [], 'add_status_to_orders_table.php', "\$this->schema()->table('orders'"],
            'table option' => ['change_orders', ['--table' => 'orders'], 'change_orders.php', "\$this->schema()->table('orders'"],
        ];
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function stubsProvider(): array
    {
        return array_map(fn (array $migration): array => [$migration[0], $migration[1]], self::migrationsProvider());
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function runnableMigrationsProvider(): array
    {
        return [
            'update guessed from the name' => ['add_status_to_seed_runs_table', []],
            'table option' => ['change_seed_runs', ['--table' => 'seed_runs']],
        ];
    }
}
