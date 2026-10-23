<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Seeds;

use Hyde1\EloquentMigrations\Seeds\Seeder;
use Hyde1\EloquentMigrations\Seeds\SeederLoader;
use PHPUnit\Framework\TestCase;

/**
 * Seeder classes are loaded with require_once: fixtures are loaded from their real path, as in the integration tests,
 * so that each class keeps a single file path for the whole test run.
 */
final class SeederLoaderTest extends TestCase
{
    private const string FIXTURES_PATH = __DIR__ . '/../../Fixtures/seeders';

    public function testLoadsTheSeedersOfTheDirectoryInAlphabeticalOrder(): void
    {
        // When
        $seeders = new SeederLoader()->load(self::fixturePath('dependencies'));

        // Then
        $this->assertSame(
            ['Database\Seeders\CommentSeeder', 'Database\Seeders\PostSeeder', 'Database\Seeders\UserSeeder'],
            array_keys($seeders),
        );
        $this->assertSame(array_keys($seeders), array_values(array_map(fn (Seeder $seeder): string => $seeder::class, $seeders)));
    }

    public function testReturnsNoSeederForAMissingDirectory(): void
    {
        $this->assertSame([], new SeederLoader()->load(self::fixturePath('dependencies') . '/missing'));
    }

    public function testRejectsAFileThatDoesNotDeclareTheExpectedClass(): void
    {
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find class "Database\Seeders\MisnamedSeeder"');

        // When
        new SeederLoader()->load(self::fixturePath('misnamed_class'));
    }

    public function testRejectsAClassThatDoesNotExtendSeeder(): void
    {
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "Database\Seeders\NotASeeder" in file');

        // When
        new SeederLoader()->load(self::fixturePath('not_a_seeder'));
    }

    private static function fixturePath(string $scenario): string
    {
        return (string) realpath(self::FIXTURES_PATH . '/' . $scenario);
    }
}
