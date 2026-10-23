<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->table('seed_runs')->insert(['name' => 'user']);
    }
}
