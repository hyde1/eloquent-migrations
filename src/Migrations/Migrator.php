<?php

namespace Hyde1\EloquentMigrations\Migrations;

class Migrator extends \Illuminate\Database\Migrations\Migrator
{
    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     * @return object
     */
    public function resolve($file)
    {
        $migration = parent::resolve($file);
        $migration->db = $this->resolveConnection(
            $migration->getConnection(),
        );
        return $migration;
    }

    /**
     * Resolve a migration instance from a migration path.
     *
     * @param  string  $path
     * @return object
     */
    protected function resolvePath(string $path)
    {
        $migration = parent::resolvePath($path);
        $migration->db = $this->resolveConnection(
            $migration->getConnection(),
        );
        return $migration;
    }

    /**
     * Get the migrations for a rollback operation.
     *
     * Adds the "migration" option, to rollback a single migration whatever its batch.
     *
     * @param  array<string, mixed>  $options
     * @return list<object>
     */
    protected function getMigrationsForRollback(array $options): array
    {
        $migration = $options['migration'] ?? null;
        if (!is_string($migration) || $migration === '') {
            return parent::getMigrationsForRollback($options);
        }

        return in_array($migration, $this->repository->getRan(), true)
            ? [(object) ['migration' => $migration]]
            : [];
    }
}
