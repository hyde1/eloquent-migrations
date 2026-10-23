<?php

// The namespace differs in case from the expected one, which PHP accepts

namespace Database\seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class CasingASeeder extends Seeder
{
    /** @return list<class-string<Seeder>> */
    public function getDependencies(): array
    {
        return [
            CasingZSeeder::class,
        ];
    }

    public function run(): void {}
}
