<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Support;

use Illuminate\Filesystem\Filesystem;

/**
 * Writes the files generated in projects (config file, binary, seeders) from the templates shipped with the library.
 */
final class TemplateWriter
{
    private const string LIBRARY_ROOT = __DIR__ . '/../..';

    /**
     * Path of a file shipped with the library, given relatively to its root (e.g. "data/stubs").
     */
    public static function path(string $relativePath): string
    {
        return self::LIBRARY_ROOT . '/' . $relativePath;
    }

    /**
     * @param string $template Path relative to the library root
     * @param array<string, string> $replacements Replacement indexed by the text it replaces
     */
    public function write(string $template, string $destination, array $replacements = []): void
    {
        $contents = file_get_contents(self::path($template));
        if ($contents === false) {
            throw new \RuntimeException(sprintf('Could not read the template "%s"', $template));
        }

        // Like the migrations directory, which MigrationCreator creates on demand
        new Filesystem()->ensureDirectoryExists(dirname($destination));

        if (file_put_contents($destination, strtr($contents, $replacements)) === false) {
            throw new \RuntimeException(sprintf('Could not write the file "%s"', $destination));
        }
    }
}
