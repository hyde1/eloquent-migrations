<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class EggSeeder extends Seeder
{
    /** @return list<class-string<Seeder>> */
    public function getDependencies(): array
    {
        return [
            ChickenSeeder::class,
        ];
    }

    public function run(): void
    {
        $this->db()->table('seed_runs')->insert(['name' => 'egg']);
    }
}
