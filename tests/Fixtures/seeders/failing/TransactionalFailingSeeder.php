<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class TransactionalFailingSeeder extends Seeder
{
    public function run(): void
    {
        $this->table('seed_runs')->insert(['name' => 'transactional']);

        throw new \RuntimeException('Seeder failed on purpose');
    }
}
