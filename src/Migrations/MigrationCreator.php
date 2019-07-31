<?php

namespace Hyde1\EloquentMigrations\Migrations;

class MigrationCreator extends \Illuminate\Database\Migrations\MigrationCreator
{
    /**
     * Get the path to the stubs.
     *
     * @return string
     */
    public function stubPath()
    {
        return __DIR__.'/../../data/stubs';
    }
}
