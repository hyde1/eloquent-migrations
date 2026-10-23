<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\DataProvider;

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
     *
     * @param array<string, string> $options
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
     * End-to-end check of the stubs: generated, applied then rolled back without editing them.
     * Checks the migration is recorded then removed from the repository.
     *
     * @param array<string, string> $options
     */
    #[DataProvider('stubsProvider')]
    public function testGeneratedMigrationCanBeMigratedAndRolledBack(string $name, array $options): void
    {
        // Given
        $this->runCommand('create', ['name' => $name] + $options);
        $file = $this->generatedMigration();
        $migration = basename($file, '.php');
        if (str_contains((string) file_get_contents($file), "\$this->schema()->table('orders'")) {
            // The update stubs alter an existing table
            $this->schema()->create('orders', function (Blueprint $table): void {
                $table->increments('id');
            });
        }

        // When
        $this->runCommand('migrate');
        $ranAfterMigrate = $this->ranMigrations();
        $this->runCommand('rollback');

        // Then
        $this->assertArrayHasKey($migration, $ranAfterMigrate);
        $this->assertArrayNotHasKey($migration, $this->ranMigrations());
    }

    /**
     * The stubs show how to run a migration outside of a transaction (e.g. CREATE INDEX CONCURRENTLY), by uncommenting
     * a line. The Eloquent parent property is untyped, so the hint must not add a type, which would be a fatal error.
     * Checks each generated migration still loads once the line is uncommented, and opts out of the transaction.
     *
     * @param array<string, string> $options
     */
    #[DataProvider('stubsProvider')]
    public function testGeneratedMigrationCanOptOutOfTheTransaction(string $name, array $options): void
    {
        // Given
        $this->runCommand('create', ['name' => $name] + $options);
        $file = $this->generatedMigration();
        file_put_contents($file, str_replace('//public $withinTransaction', 'public $withinTransaction', (string) file_get_contents($file), $replacements));
        $this->assertSame(1, $replacements, 'The stub should contain the commented withinTransaction hint');

        // When
        $inspection = $this->inspectPhpFile($file);

        // Then
        $this->assertSame(0, $inspection['exitCode'], $inspection['output']);
        $this->assertFalse($inspection['within_transaction'] ?? null);
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function generatedMigration(): string
    {
        $files = glob($this->migrationsDirectory() . '/*.php') ?: [];
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
            'blank migration' => ['backfill_data', [], 'backfill_data.php', "public function up(): void\n    {\n        //\n    }"],
        ];
    }

    /** @return array<string, array{string, array<string, string>}> */
    public static function stubsProvider(): array
    {
        return array_map(fn (array $migration): array => [$migration[0], $migration[1]], self::migrationsProvider());
    }
}
