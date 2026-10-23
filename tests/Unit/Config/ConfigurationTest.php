<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Config;

use Hyde1\EloquentMigrations\Config\Configuration;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    private const string FILE = '/project/elmigrator.php';

    public function testReadsTheValuesOfTheConfigurationFile(): void
    {
        // Given
        $values = $this->makeValues();
        $configuration = new Configuration($values, self::FILE);

        // Then
        $this->assertSame('database/migrations', $configuration->migrationsPath());
        $this->assertSame('database/seeders', $configuration->seedsPath());
        $this->assertSame('el_migrations', $configuration->migrationTable());
        $this->assertSame('development', $configuration->defaultEnvironment());
        $this->assertSame($values['db'], $configuration->databaseManager());
        $this->assertNull($configuration->connectionName());
        $this->assertSame($values, $configuration->toArray());
        $this->assertSame(self::FILE, $configuration->file());
    }

    public function testReadsTheOptionalConnectionName(): void
    {
        $this->assertSame('secondary', new Configuration($this->makeValues(['database' => 'secondary']), self::FILE)->connectionName());
    }

    /**
     * A missing value used to raise an "Undefined array key" warning, then a TypeError or an empty path.
     * Checks the error names the file and the missing key.
     *
     * @param \Closure(Configuration): mixed $read
     */
    #[DataProvider('requiredValuesProvider')]
    public function testFailsExplicitlyOnAMissingValue(string $key, \Closure $read): void
    {
        // Given
        $values = $this->makeValues();
        if (str_starts_with($key, 'paths.')) {
            unset($values['paths'][substr($key, strlen('paths.'))]);
        } else {
            unset($values[$key]);
        }

        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The configuration file "%s" must define "%s"', self::FILE, $key));

        // When
        $read(new Configuration($values, self::FILE));
    }

    /**
     * Checks a value is only required when read: a command does not fail on the values it does not use.
     */
    public function testDoesNotRequireTheValuesThatAreNotRead(): void
    {
        $this->assertSame('database/migrations', new Configuration(['paths' => ['migrations' => 'database/migrations']], self::FILE)->migrationsPath());
    }

    public function testRejectsADatabaseManagerOfAnotherType(): void
    {
        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The "db" value of the configuration file "%s" must be an %s', self::FILE, DatabaseManager::class));

        // When
        new Configuration($this->makeValues(['db' => new Manager()]), self::FILE)->databaseManager();
    }

    /**
     * These values used to be stored in string properties, so a value of another type was rejected. Checks it still
     * is, e.g. false must not become an empty environment, which would skip the production confirmation.
     *
     * @param \Closure(Configuration): mixed $read
     */
    #[DataProvider('strictStringValuesProvider')]
    public function testRejectsAValueThatIsNotAString(string $key, mixed $value, \Closure $read): void
    {
        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(sprintf('The "%s" value of the configuration file "%s" must be a string', $key, self::FILE));

        // When
        $read(new Configuration($this->makeValues([$key => $value]), self::FILE));
    }

    /**
     * The paths and the migration table used to be cast to string.
     */
    public function testConvertsTheScalarPathsAndMigrationTableToString(): void
    {
        $this->assertSame('1', new Configuration($this->makeValues(['migration_table' => 1]), self::FILE)->migrationTable());
    }

    public function testRejectsAPathThatIsNotAString(): void
    {
        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('The "paths.migrations" value of the configuration file "/project/elmigrator.php" must be a string');

        // When
        new Configuration($this->makeValues(['paths' => ['migrations' => ['database/migrations']]]), self::FILE)->migrationsPath();
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * Shaped like data/elmigrator.php.dist.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeValues(array $overrides = []): array
    {
        return array_replace([
            'default_environment' => 'development',
            'paths' => [
                'migrations' => 'database/migrations',
                'seeds' => 'database/seeders',
            ],
            'migration_table' => 'el_migrations',
            'db' => new Manager()->getDatabaseManager(),
        ], $overrides);
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{string, mixed, \Closure(Configuration): mixed}> */
    public static function strictStringValuesProvider(): array
    {
        return [
            'false default environment' => ['default_environment', false, fn (Configuration $configuration): string => $configuration->defaultEnvironment()],
            'integer connection name' => ['database', 0, fn (Configuration $configuration): ?string => $configuration->connectionName()],
            'false connection name' => ['database', false, fn (Configuration $configuration): ?string => $configuration->connectionName()],
        ];
    }

    /** @return array<string, array{string, \Closure(Configuration): mixed}> */
    public static function requiredValuesProvider(): array
    {
        return [
            'migrations path' => ['paths.migrations', fn (Configuration $configuration): string => $configuration->migrationsPath()],
            'seeds path' => ['paths.seeds', fn (Configuration $configuration): string => $configuration->seedsPath()],
            'migration table' => ['migration_table', fn (Configuration $configuration): string => $configuration->migrationTable()],
            'default environment' => ['default_environment', fn (Configuration $configuration): string => $configuration->defaultEnvironment()],
            'database manager' => ['db', fn (Configuration $configuration): DatabaseManager => $configuration->databaseManager()],
        ];
    }
}
