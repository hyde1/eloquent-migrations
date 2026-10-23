<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Seeds;

/**
 * Instantiates the seeders of a directory: each file declares a class named after it, in the seeders namespace.
 */
final class SeederLoader
{
    public const string NAMESPACE = 'Database\\Seeders\\';

    /**
     * @return array<string, Seeder> Seeders indexed by class name, in the alphabetical order of their files
     */
    public function load(string $directory): array
    {
        $seeders = [];
        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $className = self::NAMESPACE . pathinfo($file, PATHINFO_FILENAME);

            require_once $file;

            if (!class_exists($className)) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not find class "%s" in file "%s"',
                    $className,
                    $file,
                ));
            }

            $seeder = new $className();
            if (!($seeder instanceof Seeder)) {
                throw new \InvalidArgumentException(sprintf(
                    'The class "%s" in file "%s" must extend \Hyde1\EloquentMigrations\Seeds\Seeder',
                    $className,
                    $file,
                ));
            }
            $seeders[$className] = $seeder;
        }

        ksort($seeders);

        // Indexed by their declared class name, as their dependencies (::class): PHP class names are case-insensitive,
        // a file may declare the namespace with another case
        $seedersByClassName = [];
        foreach ($seeders as $seeder) {
            $seedersByClassName[$seeder::class] = $seeder;
        }

        return $seedersByClassName;
    }
}
