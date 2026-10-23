<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support\Doubles;

use Hyde1\EloquentMigrations\Seeds\Seeder;

/**
 * Seeder with the given dependencies, to order seeders without declaring a class for each one.
 */
final class DependentSeeder extends Seeder
{
    /**
     * @param list<string> $dependencies
     */
    public function __construct(private readonly array $dependencies = []) {}

    /** @return list<string> */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function run(): void {}
}
