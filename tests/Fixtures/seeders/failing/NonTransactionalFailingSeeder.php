<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class NonTransactionalFailingSeeder extends Seeder
{
    public bool $withinTransaction = false;

    public function run(): void
    {
        $this->table('seed_runs')->insert(['name' => 'non_transactional']);

        throw new \RuntimeException('Seeder failed on purpose');
    }
}
