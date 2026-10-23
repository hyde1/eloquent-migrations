<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Migrations;

use Hyde1\EloquentMigrations\Migrations\Migration;
use Hyde1\EloquentMigrations\Tests\Support\Doubles\ExposedMigration;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

final class MigrationTest extends TestCase
{
    /**
     * Eloquent decides to wrap a migration in a transaction by reading this public property.
     * Checks it is still inherited from Eloquent with the default value expected by existing migrations.
     */
    public function testRunsWithinTransactionByDefault(): void
    {
        $this->assertTrue($this->makeMigration()->withinTransaction);
    }

    public function testUsesTheDefaultConnectionWhenNoneIsDeclared(): void
    {
        $this->assertNull($this->makeMigration()->getConnection());
    }

    public function testDbAndSchemaUseTheInjectedConnection(): void
    {
        // Given
        $connection = $this->makeSqliteConnection();
        $migration = $this->makeMigration();

        // When
        $migration->db = $connection;

        // Then
        $this->assertSame($connection, $migration->exposedDb());
        $this->assertSame($connection, $migration->exposedSchema()->getConnection());
    }

    /**
     * Migrations generated from the stubs and written in projects declare up() and down() with a void return type.
     * Checks the abstract signatures still match, otherwise every existing migration would raise a fatal error.
     */
    public function testDeclaresVoidUpAndDownMethods(): void
    {
        $class = new \ReflectionClass(Migration::class);

        foreach (['up', 'down'] as $method) {
            $reflectionMethod = $class->getMethod($method);
            $this->assertTrue($reflectionMethod->isAbstract(), sprintf('%s() should be abstract', $method));
            $this->assertTrue($reflectionMethod->isPublic(), sprintf('%s() should be public', $method));
            $this->assertSame('void', (string) $reflectionMethod->getReturnType());
            $this->assertSame(0, $reflectionMethod->getNumberOfParameters());
        }
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function makeMigration(): ExposedMigration
    {
        return new ExposedMigration();
    }

    private function makeSqliteConnection(): Connection
    {
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);

        return $capsule->getConnection();
    }
}
