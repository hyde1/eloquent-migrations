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
            $migration->getConnection()
        );
		return $migration;
    }
}
