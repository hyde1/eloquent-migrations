<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class CreateSeedCommandTest extends IntegrationTestCase
{
    public function testGeneratesASeederClassFromTheTemplate(): void
    {
        // When
        $tester = $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);

        // Then
        $this->assertCommandSucceeded($tester);
        $file = $this->seedsDirectory() . '/CustomerSeeder.php';
        $this->assertFileExists($file);
        $this->assertStringContainsString('Seed created', $tester->getDisplay());

        $code = (string) file_get_contents($file);
        $this->assertStringContainsString('namespace Database\Seeders;', $code);
        $this->assertStringContainsString('class CustomerSeeder extends Seeder', $code);
        $this->assertStringNotContainsString('DummySeeder', $code);
    }

    /**
     * In a new project the seeds directory may not exist yet, like the migrations one which create makes on demand.
     */
    public function testCreatesTheSeedsDirectoryWhenMissing(): void
    {
        // Given
        rmdir($this->seedsDirectory());

        // When
        $tester = $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertFileExists($this->seedsDirectory() . '/CustomerSeeder.php');
    }

    #[DataProvider('invalidNamesProvider')]
    public function testRejectsAnInvalidClassName(string $name): void
    {
        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('This seeder name is not a valid PHP CamelCase Class name');

        // When
        $this->runCommand('seed:create', ['name' => $name]);
    }

    public function testRefusesToOverwriteAnExistingSeeder(): void
    {
        // Given
        $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);
        file_put_contents($this->seedsDirectory() . '/CustomerSeeder.php', '<?php // edited');

        // When
        try {
            $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);
            $this->fail('An existing seeder should not be overwritten');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('This seeder name is already taken: CustomerSeeder', $exception->getMessage());
        }

        // Then
        $this->assertSame('<?php // edited', file_get_contents($this->seedsDirectory() . '/CustomerSeeder.php'));
    }

    /**
     * End-to-end check of the seeder template: generated then run by seed:run without being edited.
     * Checks the generated seeder is loaded and run.
     */
    public function testGeneratedSeederCanBeRun(): void
    {
        // Given
        // Seeders are loaded in the test process: the class name must be unique
        $className = 'GeneratedSeeder' . bin2hex(random_bytes(4));
        $this->runCommand('seed:create', ['name' => $className]);

        // When
        $tester = $this->runCommand('seed:run');

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertStringContainsString('Database\\Seeders\\' . $className . ' seeded', $tester->getDisplay());
    }

    /**
     * The template shows how to declare dependencies and to opt out of the transaction, by uncommenting code.
     * Checks the generated seeder still loads once uncommented, with the expected values.
     */
    public function testGeneratedSeederHintsAreValid(): void
    {
        // Given
        $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);
        $file = $this->seedsDirectory() . '/CustomerSeeder.php';
        $code = str_replace('//public bool', 'public bool', (string) file_get_contents($file));
        $code = (string) preg_replace('#/\* Uncomment to add dependencies\n(.*?)\n\s*\*/#s', '$1', $code);
        file_put_contents($file, $code);

        // When
        $inspection = $this->inspectPhpFile($file);

        // Then
        $this->assertSame(0, $inspection['exitCode'], $inspection['output']);
        $this->assertSame(['Database\\Seeders\\CustomerSeeder'], $inspection['declared_seeders'] ?? null);
        $this->assertSame([
            'Database\\Seeders\\CustomerSeeder' => [
                'dependencies' => ['Database\\Seeders\\UserSeeder'],
                'within_transaction' => false,
            ],
        ], $inspection['seeders'] ?? null);
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{string}> */
    public static function invalidNamesProvider(): array
    {
        return [
            'starts with a lowercase letter' => ['customerSeeder'],
            'contains a dash' => ['Customer-Seeder'],
            'contains a namespace' => ['Seeders\CustomerSeeder'],
            'starts with a digit' => ['1Seeder'],
        ];
    }
}
