<?php

namespace Hyde1\EloquentMigrations\Seeds;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

abstract class Seeder
{
    /**
     * Enables, if supported, wrapping the migration within a transaction.
     */
    public bool $withinTransaction = true;

    /**
     * Return array of Seeds that needs to be run before
     *
     * @return array
     */
    public function getDependencies(): array
    {
        return [];
    }

    private Connection $db;

    public function setDb(Connection $db): void
    {
        $this->db = $db;
    }

    protected function getDb(): Connection
    {
        return $this->db;
    }

    protected function db(): Connection
    {
        return $this->db;
    }

    public function getName(): string
    {
        return get_class($this);
    }

    public function table(string $name): Builder
    {
        return $this->getDb()->table($name);
    }

    /**
     * Run the database seeds.
     */
    abstract public function run(): void;
}
