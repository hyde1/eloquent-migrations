<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * app/elmigrator.php looks for the Composer autoloader of the project installing the library first, then for the
 * library's own one (development). Each test runs a copy of the file in a fake project, in a separate process.
 */
final class ApplicationAutoloadTest extends TestCase
{
    private const string APPLICATION_FILE = __DIR__ . '/../../app/elmigrator.php';
    private const string REAL_AUTOLOADER = __DIR__ . '/../../vendor/autoload.php';

    private string $projectDirectory;
    private string $libraryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'elmigrator-autoload-' . bin2hex(random_bytes(8));
        $this->libraryDirectory = $this->projectDirectory . '/vendor/hyde1/eloquent-migrations';
        mkdir($this->libraryDirectory . '/app', 0o777, true);
        copy(self::APPLICATION_FILE, $this->libraryDirectory . '/app/elmigrator.php');
    }

    protected function tearDown(): void
    {
        new Filesystem()->deleteDirectory($this->projectDirectory);

        parent::tearDown();
    }

    /**
     * A library installed with its own vendor directory (e.g. a local clone) has two autoloaders registering
     * different copies of the same classes. Checks only the project one is loaded.
     */
    public function testLoadsOnlyTheProjectAutoloaderWhenSeveralExist(): void
    {
        // Given
        $this->writeAutoloader($this->projectDirectory . '/vendor/autoload.php', 'project');
        $this->writeAutoloader($this->libraryDirectory . '/vendor/autoload.php', 'library');

        // When
        $result = $this->runApplicationFile();

        // Then
        $this->assertSame(0, $result['exitCode'], $result['output']);
        $this->assertSame('project autoloader loaded', $result['output']);
    }

    public function testFailsWithAnExplicitMessageWhenNoAutoloaderIsFound(): void
    {
        // When
        $result = $this->runApplicationFile();

        // Then
        $this->assertSame(1, $result['exitCode']);
        $this->assertStringContainsString('Could not find the Composer autoloader, run composer install', $result['output']);
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    /**
     * Writes an autoloader reporting its loading, which delegates to the real one to keep the application working.
     */
    private function writeAutoloader(string $file, string $name): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0o777, true);
        }
        file_put_contents($file, sprintf(
            "<?php\necho %s;\nrequire %s;\n",
            var_export($name . ' autoloader loaded', true),
            var_export((string) realpath(self::REAL_AUTOLOADER), true),
        ));
    }

    /**
     * @return array{exitCode: int, output: string}
     */
    private function runApplicationFile(): array
    {
        $command = implode(' ', array_map(escapeshellarg(...), [PHP_BINARY, $this->libraryDirectory . '/app/elmigrator.php']));
        exec($command . ' 2>&1', $output, $exitCode);

        return ['exitCode' => $exitCode, 'output' => implode(PHP_EOL, $output)];
    }
}
