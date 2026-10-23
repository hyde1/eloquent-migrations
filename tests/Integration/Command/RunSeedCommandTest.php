<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;

final class RunSeedCommandTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->useMigrations('seed_runs');
        $this->runCommand('migrate');
    }

    /**
     * Seeders declare the seeders they depend on with getDependencies(), they are loaded in alphabetical order.
     * Checks every seeder runs exactly once, after its dependencies (Comment depends on Post which depends on User).
     */
    public function testRunsAllSeedersInDependencyOrder(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);

        // When
        $tester = $this->runCommand('seed:run');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame(['user', 'post', 'comment'], $this->seedRuns());
        $this->assertStringContainsString('All seed completed.', $tester->getDisplay());
    }

    /**
     * @param list<string> $seeders
     * @param list<string> $expectedRuns
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('selectedSeedersProvider')]
    public function testRunsOnlyTheGivenSeeders(array $seeders, array $expectedRuns): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);

        // When
        $tester = $this->runCommand('seed:run', ['--seed' => $seeders]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame($expectedRuns, $this->seedRuns());
    }

    public function testFailsOnAnUnknownSeeder(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);

        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The seed class "Database\Seeders\UnknownSeeder" does not exist');

        // When
        $this->runCommand('seed:run', ['--seed' => ['Database\Seeders\UnknownSeeder']]);
    }

    /**
     * Seeders run within a transaction by default, so a failing seeder does not leave partial data.
     * Checks the rows inserted before the failure are rolled back.
     */
    public function testRollsBackAFailingSeederRunWithinATransaction(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('failing')]);

        // When
        $this->runFailingSeeder('Database\Seeders\TransactionalFailingSeeder');

        // Then
        $this->assertSame([], $this->seedRuns());
    }

    public function testKeepsTheDataOfAFailingSeederRunWithoutTransaction(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('failing')]);

        // When
        $this->runFailingSeeder('Database\Seeders\NonTransactionalFailingSeeder');

        // Then
        $this->assertSame(['non_transactional'], $this->seedRuns());
    }

    public function testRejectsAClassThatDoesNotExtendSeeder(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('not_a_seeder')]);

        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The class "Database\Seeders\NotASeeder" in file');

        // When
        $this->runCommand('seed:run');
    }

    /**
     * Seeder files are expected to declare a class named after the file, in the Database\Seeders namespace.
     * Checks a file declaring another class is reported instead of being silently skipped.
     */
    public function testRejectsAFileThatDoesNotDeclareTheExpectedClass(): void
    {
        // Given
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('misnamed_class')]);

        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Could not find class "Database\Seeders\MisnamedSeeder"');

        // When
        $this->runCommand('seed:run');
    }

    public function testRunsSeedersOnTheConnectionGivenAsOption(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::SQLITE);
        $this->writeConfig(options: ['seeds' => self::seedersFixturePath('dependencies')]);
        $this->runCommand('migrate', ['--database' => 'secondary']);

        // When
        $this->runCommand('seed:run', ['--database' => 'secondary', '--seed' => ['Database\Seeders\UserSeeder']]);

        // Then
        $this->assertSame([], $this->seedRuns());
        $this->assertSame(['user'], $this->seedRuns('secondary'));
    }

    public function testAsksForConfirmationInProduction(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production', 'seeds' => self::seedersFixturePath('dependencies')]);

        // When
        $tester = $this->runCommand('seed:run');

        // Then
        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Command Cancelled!', $tester->getDisplay());
        $this->assertSame([], $this->seedRuns());
    }

    public function testRunsInProductionWithForce(): void
    {
        // Given
        $this->writeConfig(options: ['environment' => 'production', 'seeds' => self::seedersFixturePath('dependencies')]);

        // When
        $tester = $this->runCommand('seed:run', ['--force' => true]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertSame(['user', 'post', 'comment'], $this->seedRuns());
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * @return array<mixed> Names of the seeders that ran, in execution order
     */
    private function seedRuns(string $connection = 'default'): array
    {
        return $this->db($connection)->table('seed_runs')->orderBy('id')->pluck('name')->all();
    }

    private function runFailingSeeder(string $seeder): void
    {
        try {
            $this->runCommand('seed:run', ['--seed' => [$seeder]]);
        } catch (\RuntimeException $exception) {
            $this->assertSame('Seeder failed on purpose', $exception->getMessage());

            return;
        }

        $this->fail('The seeder error should have been propagated');
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{list<string>, list<string>}> */
    public static function selectedSeedersProvider(): array
    {
        return [
            'single seeder, dependencies are not run' => [['Database\Seeders\CommentSeeder'], ['comment']],
            'several seeders, in the given order' => [['Database\Seeders\PostSeeder', 'Database\Seeders\UserSeeder'], ['post', 'user']],
            'short class name' => [['CommentSeeder'], ['comment']],
        ];
    }
}
