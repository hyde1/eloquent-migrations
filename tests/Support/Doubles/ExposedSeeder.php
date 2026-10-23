<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support\Doubles;

use Hyde1\EloquentMigrations\Seeds\Seeder;
use Illuminate\Database\Connection;

/**
 * Concrete seeder exposing the protected helpers offered to project seeders.
 */
final class ExposedSeeder extends Seeder
{
    public function run(): void {}

    public function exposedDb(): Connection
    {
        return $this->db();
    }

    public function exposedGetDb(): Connection
    {
        return $this->getDb();
    }
}
