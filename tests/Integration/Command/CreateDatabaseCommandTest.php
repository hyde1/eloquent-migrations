<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use Hyde1\EloquentMigrations\Tests\Support\TestDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Command\Command;

final class CreateDatabaseCommandTest extends IntegrationTestCase
{
    private ?string $createdDatabase = null;

    protected function tearDown(): void
    {
        if ($this->createdDatabase !== null) {
            $this->db()->statement('DROP DATABASE IF EXISTS ' . $this->db()->getQueryGrammar()->wrap($this->createdDatabase));
        }

        parent::tearDown();
    }

    /**
     * Used by CI pipelines to create a test database before running migrate:fresh.
     * Checks the database exists on the server after the command.
     */
    public function testCreatesTheDatabase(): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::PGSQL, TestDatabase::MYSQL);
        $databaseName = 'elmigrator_created_' . bin2hex(random_bytes(4));
        $this->createdDatabase = $databaseName;

        // When
        $tester = $this->runCommand('create:database', ['name' => $databaseName]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString(sprintf('Database `%s` created successfully', $databaseName), $tester->getDisplay());
        $this->assertTrue($this->databaseExists($databaseName));
    }

    /**
     * Accepted names that are not plain SQL identifiers (dash, leading digit) are invalid unquoted.
     * Checks the database is created with the exact given name.
     */
    #[DataProvider('namesToQuoteProvider')]
    public function testCreatesADatabaseWhoseNameMustBeQuoted(string $namePattern): void
    {
        // Given
        $this->skipUnlessDriver(TestDatabase::PGSQL, TestDatabase::MYSQL);
        $databaseName = sprintf($namePattern, bin2hex(random_bytes(4)));
        $this->createdDatabase = $databaseName;

        // When
        $tester = $this->runCommand('create:database', ['name' => $databaseName]);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTrue($this->databaseExists($databaseName));
    }

    /**
     * The database name is concatenated to the SQL statement, so only a safe charset is accepted.
     * Checks an unsafe name is rejected with the "invalid" exit code before reaching the database.
     */
    #[DataProvider('invalidNamesProvider')]
    public function testRejectsAnInvalidName(string $name): void
    {
        // When
        $tester = $this->runCommand('create:database', ['name' => $name]);

        // Then
        $this->assertSame(Command::INVALID, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid database name', $tester->getDisplay());
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function databaseExists(string $name): bool
    {
        return match (TestDatabase::driver()) {
            TestDatabase::PGSQL => $this->db()->selectOne('select 1 as found from pg_database where datname = ?', [$name]) !== null,
            TestDatabase::MYSQL => $this->db()->selectOne('select 1 as found from information_schema.schemata where schema_name = ?', [$name]) !== null,
            default => throw new \LogicException('Databases cannot be created with ' . TestDatabase::driver()),
        };
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{string}> */
    public static function namesToQuoteProvider(): array
    {
        return [
            'dash' => ['elmigrator-created-%s'],
            'leading digit' => ['1elmigrator_created_%s'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function invalidNamesProvider(): array
    {
        return [
            'sql injection' => ['test; DROP TABLE users'],
            'space' => ['my database'],
            'quote' => ["my'database"],
            'dot' => ['my.database'],
        ];
    }
}
