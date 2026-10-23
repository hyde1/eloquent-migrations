<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Config;

use Illuminate\Database\DatabaseManager;

/**
 * Values of a configuration file (see data/elmigrator.php.dist). They are checked when read: a command only fails on
 * the values it uses.
 */
final readonly class Configuration
{
    /**
     * @param array<string, mixed> $values
     */
    public function __construct(private array $values, private string $file) {}

    public function migrationsPath(): string
    {
        return $this->getString('paths', 'migrations');
    }

    public function seedsPath(): string
    {
        return $this->getString('paths', 'seeds');
    }

    public function migrationTable(): string
    {
        return $this->getString('migration_table');
    }

    public function defaultEnvironment(): string
    {
        return $this->getStrictString('default_environment');
    }

    /**
     * Optional, the default connection of the database manager is used without it.
     */
    public function connectionName(): ?string
    {
        return $this->get('database') === null ? null : $this->getStrictString('database');
    }

    public function databaseManager(): DatabaseManager
    {
        $db = $this->getRequired('db');
        if (!$db instanceof DatabaseManager) {
            throw $this->invalidValue('db', 'an ' . DatabaseManager::class . ' (e.g. $capsule->getDatabaseManager())');
        }

        return $db;
    }

    public function file(): string
    {
        return $this->file;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    private function getString(string ...$keys): string
    {
        $value = $this->getRequired(...$keys);
        if (!is_scalar($value) && !$value instanceof \Stringable) {
            throw $this->invalidValue(implode('.', $keys), 'a string');
        }

        return (string) $value;
    }

    /**
     * For the values stored in string properties before: other types are not converted, e.g. false must not become an
     * empty environment, which would skip the production confirmation.
     */
    private function getStrictString(string $key): string
    {
        $value = $this->getRequired($key);
        if (!is_string($value)) {
            throw $this->invalidValue($key, 'a string');
        }

        return $value;
    }

    private function getRequired(string ...$keys): mixed
    {
        $value = $this->get(...$keys);
        if ($value === null) {
            throw new \RuntimeException(sprintf('The configuration file "%s" must define "%s"', $this->file, implode('.', $keys)));
        }

        return $value;
    }

    private function get(string ...$keys): mixed
    {
        $value = $this->values;
        foreach ($keys as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    private function invalidValue(string $key, string $expected): \RuntimeException
    {
        return new \RuntimeException(sprintf('The "%s" value of the configuration file "%s" must be %s', $key, $this->file, $expected));
    }
}
