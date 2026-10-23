<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support;

use Illuminate\Database\Capsule\Manager;

/**
 * Builds the database connection settings used by the test suites.
 *
 * The driver is selected with the DB_CONNECTION environment variable (sqlite, pgsql or mysql),
 * the other DB_* variables override the defaults matching docker-compose.yml and the CI services.
 */
final class TestDatabase
{
    public const string SQLITE = 'sqlite';
    public const string PGSQL = 'pgsql';
    public const string MYSQL = 'mysql';

    public static function driver(): string
    {
        $driver = self::env('DB_CONNECTION', self::SQLITE);

        if (!in_array($driver, [self::SQLITE, self::PGSQL, self::MYSQL], true)) {
            throw new \RuntimeException(sprintf('Unsupported DB_CONNECTION "%s"', $driver));
        }

        return $driver;
    }

    public static function isSqlite(): bool
    {
        return self::driver() === self::SQLITE;
    }

    /**
     * Connection settings, as they would be given to Capsule::addConnection() in a config file.
     *
     * With sqlite each named connection is a distinct file inside the working directory, which
     * allows tests to check that a command really targets the requested connection.
     * Other drivers point every named connection to the same database.
     *
     * @return array<string, mixed>
     */
    public static function connection(string $workingDirectory, string $name = 'default'): array
    {
        return match (self::driver()) {
            self::SQLITE => [
                'driver' => 'sqlite',
                'database' => self::sqliteFile($workingDirectory, $name),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            self::PGSQL => [
                'driver' => 'pgsql',
                'host' => self::env('DB_HOST', '127.0.0.1'),
                'port' => (int) self::env('DB_PORT', '54329'),
                'database' => self::env('DB_DATABASE', 'elmigrator'),
                'username' => self::env('DB_USERNAME', 'elmigrator'),
                'password' => self::env('DB_PASSWORD', 'elmigrator'),
                'charset' => 'utf8',
                'prefix' => '',
            ],
            self::MYSQL => [
                'driver' => 'mysql',
                'host' => self::env('DB_HOST', '127.0.0.1'),
                'port' => (int) self::env('DB_PORT', '33069'),
                'database' => self::env('DB_DATABASE', 'elmigrator'),
                'username' => self::env('DB_USERNAME', 'root'),
                'password' => self::env('DB_PASSWORD', 'elmigrator'),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
            ],
            default => throw new \LogicException('Unsupported driver'),
        };
    }

    /**
     * @param list<string> $connectionNames
     */
    public static function createCapsule(string $workingDirectory, array $connectionNames = ['default']): Manager
    {
        $capsule = new Manager();
        foreach ($connectionNames as $name) {
            $capsule->addConnection(self::connection($workingDirectory, $name), $name);
        }

        return $capsule;
    }

    public static function sqliteFile(string $workingDirectory, string $name): string
    {
        $file = $workingDirectory . DIRECTORY_SEPARATOR . $name . '.sqlite';
        if (!is_file($file)) {
            touch($file);
        }

        return $file;
    }

    private static function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }
}
