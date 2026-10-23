<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit\Support;

use Hyde1\EloquentMigrations\Support\TemplateWriter;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

final class TemplateWriterTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'elmigrator-templates-' . bin2hex(random_bytes(8));
    }

    protected function tearDown(): void
    {
        new Filesystem()->deleteDirectory($this->directory);

        parent::tearDown();
    }

    /**
     * Checks the template is copied with its replacements, in a directory created on demand.
     */
    public function testWritesTheTemplateWithItsReplacements(): void
    {
        // Given
        $destination = $this->directory . '/database/seeders/CustomerSeeder.php';

        // When
        new TemplateWriter()->write('data/Seeder.php.dist', $destination, ['DummySeeder' => 'CustomerSeeder']);

        // Then
        $this->assertSame(
            str_replace('DummySeeder', 'CustomerSeeder', (string) file_get_contents(TemplateWriter::path('data/Seeder.php.dist'))),
            file_get_contents($destination),
        );
    }

    public function testResolvesThePathsFromTheLibraryRoot(): void
    {
        $this->assertDirectoryExists(TemplateWriter::path('data/stubs'));
        $this->assertFileExists(TemplateWriter::path('bin/elmigrator'));
    }

    public function testFailsOnAnUnknownTemplate(): void
    {
        // Then
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Could not read the template "data/missing.dist"');

        // When
        @new TemplateWriter()->write('data/missing.dist', $this->directory . '/missing.php');
    }
}
