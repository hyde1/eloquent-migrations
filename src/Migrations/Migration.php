<?php

namespace Hyde1\EloquentMigrations\Migrations;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

abstract class Migration extends \Illuminate\Database\Migrations\Migration
{
    public ?Connection $db = null;

    protected function db(): ?Connection
    {
        return $this->db;
    }

    protected function schema(): Builder
    {
        return $this->db()->getSchemaBuilder();
    }
}
