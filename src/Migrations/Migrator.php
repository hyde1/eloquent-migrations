<?php

namespace Hyde1\EloquentMigrations\Migrations;

class Migrator extends \Illuminate\Database\Migrations\Migrator
{
    /**
     * Resolve a migration instance from a file.
     *
     * @param  string  $file
     */
    // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint -- signature inherited from Eloquent
    public function resolve($file): object
    {
        return $this->injectConnection(parent::resolve($file));
    }

    /**
     * Resolve a migration instance from a migration path.
     */
    protected function resolvePath(string $path): object
    {
        return $this->injectConnection(parent::resolvePath($path));
    }

    /**
     * Get the migrations for a rollback operation.
     *
     * Adds the "migration" option, to rollback a single migration whatever its batch.
     *
     * @param  array<string, mixed>  $options
     * @return array<mixed>
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

    /**
     * Give the migration the connection it runs on, used by Migration::db() and Migration::schema().
     */
    private function injectConnection(object $migration): object
    {
        if ($migration instanceof Migration) {
            // Like Eloquent's own calls: a null connection falls back to the migrator one (documented as string)
            // @phpstan-ignore argument.type
            $migration->db = $this->resolveConnection($migration->getConnection());
        }

        return $migration;
    }
}
