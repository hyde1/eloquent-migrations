<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class OrphanSeeder extends Seeder
{
    /** @return list<string> */
    public function getDependencies(): array
    {
        return [
            'Database\Seeders\MissingSeeder',
        ];
    }

    public function run(): void
    {
        $this->db()->table('seed_runs')->insert(['name' => 'orphan']);
    }
}
