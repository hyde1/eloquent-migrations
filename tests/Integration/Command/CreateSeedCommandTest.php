<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

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
     * Documents a known bug: the seeder template declares run() without the void return type required by the
     * abstract Seeder::run(), so the generated class triggers a fatal error as soon as it is loaded.
     * Checks loading the generated seeder fails with an incompatible declaration error.
     * Expected behavior: the generated seeder can be loaded and run by seed:run.
     */
    #[Group('documented-bug')]
    public function testGeneratedSeederCannotBeLoaded(): void
    {
        // Given
        $this->runCommand('seed:create', ['name' => 'CustomerSeeder']);

        // When
        $inspection = $this->inspectPhpFile($this->seedsDirectory() . '/CustomerSeeder.php');

        // Then
        $this->assertNotSame(0, $inspection['exitCode']);
        $this->assertStringContainsString('must be compatible with Hyde1\EloquentMigrations\Seeds\Seeder::run(): void', $inspection['output']);
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
