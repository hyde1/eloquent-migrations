<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Seeds;

use Hyde1\EloquentMigrations\Seeds\SeederSorter;
use Hyde1\EloquentMigrations\Tests\Support\Doubles\DependentSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SeederSorterTest extends TestCase
{
    /**
     * Projects rely on the order seed:run has always used, which is not a plain topological sort: the dependencies of a
     * seeder are moved before all the seeders ordered so far.
     *
     * @param array<string, list<string>> $dependencies Dependencies indexed by seeder name, in loading order
     * @param list<string> $expectedOrder
     */
    #[DataProvider('ordersProvider')]
    public function testOrdersSeedersAfterTheirDependencies(array $dependencies, array $expectedOrder): void
    {
        // When
        $sorted = new SeederSorter()->sort($this->makeSeeders($dependencies));

        // Then
        $this->assertSame($expectedOrder, array_keys($sorted));
    }

    /**
     * @param array<string, list<string>> $dependencies
     */
    #[DataProvider('circularDependenciesProvider')]
    public function testRejectsCircularDependencies(array $dependencies, string $expectedCycle): void
    {
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Circular dependency between seeders: ' . $expectedCycle);

        // When
        new SeederSorter()->sort($this->makeSeeders($dependencies));
    }

    public function testFindsTheDependenciesMissingFromTheSeeders(): void
    {
        // Given
        $seeders = $this->makeSeeders(['Orphan' => ['Missing', 'User'], 'User' => []]);

        // When
        $unknownDependencies = new SeederSorter()->findUnknownDependencies($seeders);

        // Then
        $this->assertSame([['Orphan', 'Missing']], $unknownDependencies);
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * @param array<string, list<string>> $dependencies
     * @return array<string, DependentSeeder>
     */
    private function makeSeeders(array $dependencies): array
    {
        return array_map(fn (array $seederDependencies): DependentSeeder => new DependentSeeder($seederDependencies), $dependencies);
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{array<string, list<string>>, list<string>}> */
    public static function ordersProvider(): array
    {
        return [
            'no dependencies, loading order kept' => [['A' => [], 'B' => [], 'C' => []], ['A', 'B', 'C']],
            'chain of dependencies' => [['Comment' => ['Post'], 'Post' => ['User'], 'User' => []], ['User', 'Post', 'Comment']],
            'dependency moved before the seeders ordered so far' => [['A' => [], 'B' => ['C'], 'C' => []], ['C', 'A', 'B']],
            'dependencies in their declared order' => [['D' => ['Y', 'X'], 'X' => [], 'Y' => []], ['Y', 'X', 'D']],
            'unknown dependency ignored' => [['Orphan' => ['Missing']], ['Orphan']],
        ];
    }

    /** @return array<string, array{array<string, list<string>>, string}> */
    public static function circularDependenciesProvider(): array
    {
        return [
            'two seeders' => [['Chicken' => ['Egg'], 'Egg' => ['Chicken']], 'Chicken -> Egg -> Chicken'],
            'through another seeder' => [['A' => ['B'], 'B' => ['C'], 'C' => ['A']], 'A -> B -> C -> A'],
            'itself' => [['A' => ['A']], 'A -> A'],
        ];
    }
}
