<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Seeds;

use Hyde1\EloquentMigrations\Seeds\Seeder;
use Hyde1\EloquentMigrations\Tests\Support\Doubles\ExposedSeeder;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Connection;
use PHPUnit\Framework\TestCase;

final class SeederTest extends TestCase
{
    public function testHasNoDependenciesByDefault(): void
    {
        $this->assertSame([], $this->makeSeeder()->getDependencies());
    }

    public function testRunsWithinTransactionByDefault(): void
    {
        $this->assertTrue($this->makeSeeder()->withinTransaction);
    }

    /**
     * The seed:run command identifies seeders (and their dependencies) by their fully qualified class name.
     * Checks the name returned by the seeder is its class name.
     */
    public function testIsNamedAfterItsClass(): void
    {
        $seeder = $this->makeSeeder();

        $this->assertSame($seeder::class, $seeder->getName());
    }

    public function testDbAccessorsUseTheInjectedConnection(): void
    {
        // Given
        $connection = $this->makeSqliteConnection();
        $seeder = $this->makeSeeder();

        // When
        $seeder->setDb($connection);

        // Then
        $this->assertSame($connection, $seeder->exposedDb());
        $this->assertSame($connection, $seeder->exposedGetDb());
    }

    public function testTableReturnsAQueryBuilderOnTheInjectedConnection(): void
    {
        // Given
        $connection = $this->makeSqliteConnection();
        $seeder = $this->makeSeeder();
        $seeder->setDb($connection);

        // When
        $builder = $seeder->table('users');

        // Then
        $this->assertSame('users', $builder->from);
        $this->assertSame($connection, $builder->getConnection());
    }

    /**
     * Seeders written in projects implement run() with a void return type.
     * Checks the abstract signature still matches, otherwise every existing seeder would raise a fatal error.
     */
    public function testDeclaresAVoidRunMethod(): void
    {
        $run = new \ReflectionMethod(Seeder::class, 'run');

        $this->assertTrue($run->isAbstract());
        $this->assertSame('void', (string) $run->getReturnType());
        $this->assertSame('array', (string) new \ReflectionMethod(Seeder::class, 'getDependencies')->getReturnType());
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function makeSeeder(): ExposedSeeder
    {
        return new ExposedSeeder();
    }

    private function makeSqliteConnection(): Connection
    {
        $capsule = new Manager();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);

        return $capsule->getConnection();
    }
}
