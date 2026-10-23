<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Seeds;

/**
 * Orders seeders so that each one runs after the seeders it depends on (Seeder::getDependencies()).
 */
final class SeederSorter
{
    /**
     * The dependencies of a seeder are moved before all the seeders ordered so far, the other seeders keep their order.
     * Dependencies missing from the given seeders are ignored, see findUnknownDependencies().
     *
     * @param array<string, Seeder> $seeders Seeders indexed by class name
     * @return array<string, Seeder>
     * @throws \InvalidArgumentException When seeders depend on each other
     */
    public function sort(array $seeders): array
    {
        return $this->order($seeders, $seeders);
    }

    /**
     * @param array<string, Seeder> $seeders Seeders indexed by class name
     * @return list<array{string, string}> Seeder and dependency class names, for each dependency missing from the seeders
     */
    public function findUnknownDependencies(array $seeders): array
    {
        $unknownDependencies = [];
        foreach ($seeders as $name => $seeder) {
            foreach ($seeder->getDependencies() as $dependency) {
                if (!array_key_exists($dependency, $seeders)) {
                    $unknownDependencies[] = [$name, $dependency];
                }
            }
        }

        return $unknownDependencies;
    }

    /**
     * @param array<string, Seeder> $seeders Seeders to order
     * @param array<string, Seeder> $allSeeders Seeders the dependencies are looked up in
     * @param list<string> $dependents Seeders whose dependencies are being ordered, to detect circular dependencies
     * @return array<string, Seeder>
     */
    private function order(array $seeders, array $allSeeders, array $dependents = []): array
    {
        $orderedSeeders = [];
        foreach ($seeders as $name => $seeder) {
            if (in_array($name, $dependents, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Circular dependency between seeders: %s',
                    implode(' -> ', [...$dependents, $name]),
                ));
            }
            // In the order they are declared
            $dependencies = [];
            foreach ($seeder->getDependencies() as $dependency) {
                if (array_key_exists($dependency, $allSeeders)) {
                    $dependencies[$dependency] = $allSeeders[$dependency];
                }
            }
            $orderedSeeders[$name] = $seeder;
            if (!empty($dependencies)) {
                $orderedSeeders = array_merge($this->order($dependencies, $allSeeders, [...$dependents, $name]), $orderedSeeders);
            }
        }

        return $orderedSeeders;
    }
}
