<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Integration\Command;

use Hyde1\EloquentMigrations\Tests\Support\IntegrationTestCase;

final class InitCommandTest extends IntegrationTestCase
{
    private const string CONFIG_TEMPLATE = __DIR__ . '/../../../data/elmigrator.php.dist';

    /**
     * init bootstraps a project: the config file goes in the given directory and a "migrator" binary in the cwd.
     * Checks both files are written and the binary loads the application from the vendor directory.
     */
    public function testCreatesTheConfigFileAndTheBinary(): void
    {
        // When
        $tester = $this->runCommand('init', ['path' => 'database']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertFileEquals(self::CONFIG_TEMPLATE, $this->workingDirectory . '/database/elmigrator.php');

        $binary = (string) file_get_contents($this->workingDirectory . '/migrator');
        $this->assertStringContainsString("require 'vendor/hyde1/eloquent-migrations/app/elmigrator.php';", $binary);
        $this->assertStringNotContainsString('__DIR__', $binary);
    }

    public function testCreatesTheGivenDirectoryWhenMissing(): void
    {
        // When
        $tester = $this->runCommand('init', ['path' => 'config']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertDirectoryExists($this->workingDirectory . '/config');
        $this->assertFileExists($this->workingDirectory . '/config/elmigrator.php');
        $this->assertStringContainsString('created config', $tester->getDisplay());
    }

    public function testOverwritesTheConfigFileOfAnExistingDirectory(): void
    {
        // When
        $tester = $this->runCommand('init', ['path' => '.']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertFileEquals(self::CONFIG_TEMPLATE, $this->workingDirectory . '/elmigrator.php');
    }

    /**
     * The README runs the generated binary directly (migrator migrate).
     */
    public function testCreatesAnExecutableBinary(): void
    {
        // When
        $tester = $this->runCommand('init', ['path' => 'database']);

        // Then
        $this->assertCommandSucceeded($tester);
        $this->assertTrue(is_executable($this->workingDirectory . '/migrator'), 'The migrator binary should be executable');
    }

    /**
     * Checks a read-only directory is reported explicitly instead of failing on a PHP warning when writing the config.
     */
    public function testRejectsAnExistingDirectoryThatIsNotWritable(): void
    {
        // Given
        mkdir($this->workingDirectory . '/readonly', 0o555);

        // Then
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The directory `readonly` is not writable');

        // When
        try {
            $this->runCommand('init', ['path' => 'readonly']);
        } finally {
            // Lets tearDown() delete the working directory
            chmod($this->workingDirectory . '/readonly', 0o755);
        }
    }
}
