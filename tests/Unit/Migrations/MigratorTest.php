<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Migrations;

use Hyde1\EloquentMigrations\Migrations\Migration;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * The library Migrator only exists to inject the resolved connection into each migration ($migration->db),
 * so that migrations can use $this->db() / $this->schema() without a Laravel container.
 */
final class MigratorTest extends TestCase
{
    private const string FIXTURES_PATH = __DIR__ . '/../../Fixtures/migrations';

    private DatabaseManager $databaseManager;
    private Migrator $migrator;

    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:'], 'secondary');
        $this->databaseManager = $capsule->getDatabaseManager();

        $this->migrator = new Migrator(
            new DatabaseMigrationRepository($this->databaseManager, 'el_migrations'),
            $this->databaseManager,
            new Filesystem(),
        );
    }

    public function testExtendsTheEloquentMigrator(): void
    {
        $this->assertInstanceOf(\Illuminate\Database\Migrations\Migrator::class, $this->migrator);
    }

    public function testInjectsTheDefaultConnectionIntoAnonymousMigrations(): void
    {
        // When
        $migration = $this->resolvePath(self::FIXTURES_PATH . '/schema/2024_01_01_000000_create_users_table.php');

        // Then
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertSame($this->databaseManager->connection('default'), $migration->db);
    }

    /**
     * A migration can target another connection through its protected $connection property.
     * Checks the injected connection is the one declared by the migration, not the default one.
     */
    public function testInjectsTheConnectionDeclaredByTheMigration(): void
    {
        // When
        $migration = $this->resolvePath(self::FIXTURES_PATH . '/connection/2024_03_01_000000_create_audit_logs_table.php');

        // Then
        $this->assertSame($this->databaseManager->connection('secondary'), $migration->db);
    }

    /**
     * The --database option of the commands relies on Migrator::usingConnection() to change the default connection.
     * Checks migrations without an explicit connection receive the connection selected this way.
     */
    public function testInjectsTheConnectionSelectedWithUsingConnection(): void
    {
        // When
        $migration = $this->migrator->usingConnection('secondary', fn (): object => $this->resolvePath(
            self::FIXTURES_PATH . '/schema/2024_01_01_000000_create_users_table.php',
        ));

        // Then
        $this->assertSame($this->databaseManager->connection('secondary'), $migration->db);
    }

    /**
     * Before anonymous migrations, migrations were named classes resolved from their file name.
     * Checks those legacy migrations still receive their connection.
     */
    public function testInjectsTheConnectionIntoClassBasedMigrations(): void
    {
        // Given
        require_once self::FIXTURES_PATH . '/class_based/2024_04_01_000000_create_legacy_table.php';

        // When
        $migration = $this->migrator->resolve('2024_04_01_000000_create_legacy_table');

        // Then
        $this->assertInstanceOf(\CreateLegacyTable::class, $migration);
        $this->assertSame($this->databaseManager->connection('secondary'), $migration->db);
    }

    public function testComputesTheMigrationNameFromItsPath(): void
    {
        $this->assertSame(
            '2024_01_01_000000_create_users_table',
            $this->migrator->getMigrationName(self::FIXTURES_PATH . '/schema/2024_01_01_000000_create_users_table.php'),
        );
    }

    public function testListsMigrationFilesSortedByName(): void
    {
        // When
        $files = $this->migrator->getMigrationFiles([self::FIXTURES_PATH . '/schema']);

        // Then
        $this->assertSame([
            '2024_01_01_000000_create_users_table',
            '2024_01_02_000000_create_posts_table',
            '2024_01_03_000000_update_users_table',
            '2024_01_04_000000_insert_default_user',
        ], array_keys($files));
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function resolvePath(string $path): object
    {
        $resolvePath = new \ReflectionMethod($this->migrator, 'resolvePath');

        $migration = $resolvePath->invoke($this->migrator, (string) realpath($path));
        $this->assertIsObject($migration);

        return $migration;
    }
}
