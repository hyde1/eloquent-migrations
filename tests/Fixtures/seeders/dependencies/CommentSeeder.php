<?php

namespace Database\Seeders;

use Hyde1\EloquentMigrations\Seeds\Seeder;

class CommentSeeder extends Seeder
{
    /** @return list<class-string<Seeder>> */
    public function getDependencies(): array
    {
        return [
            PostSeeder::class,
        ];
    }

    public function run(): void
    {
        $this->getDb()->insert('insert into seed_runs (name) values (?)', ['comment']);
    }
}
