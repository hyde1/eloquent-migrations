<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class ChickenSeeder extends Seeder
{
    /** @return list<class-string<Seeder>> */
    public function getDependencies(): array
    {
        return [
            EggSeeder::class,
        ];
    }

    public function run(): void
    {
        $this->db()->table('seed_runs')->insert(['name' => 'chicken']);
    }
}
