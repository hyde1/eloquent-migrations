<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Config;

/**
 * Finds and loads the configuration file: the given one, or else the one from the ELMIGRATOR_CONFIG environment variable.
 * Relative paths are resolved from the current working directory.
 */
final class ConfigLoader
{
    public const string ENVIRONMENT_VARIABLE = 'ELMIGRATOR_CONFIG';

    public function load(string $configFile): Configuration
    {
        $configFiles = [$this->resolvePath($configFile)];
        // $_ENV is set by custom binaries (see README), getenv() reads the variables exported in the shell
        $environmentConfigFile = $_ENV[self::ENVIRONMENT_VARIABLE] ?? getenv(self::ENVIRONMENT_VARIABLE);
        if (is_string($environmentConfigFile) && $environmentConfigFile !== '') {
            $configFiles[] = $this->resolvePath($environmentConfigFile);
        }

        foreach ($configFiles as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $values = require $file;
            if (!is_array($values)) {
                throw new \RuntimeException(sprintf('The configuration file "%s" must return an array', $file));
            }

            return new Configuration($values, $file);
        }

        throw new \RuntimeException(sprintf(
            'Could not find the configuration file "%s". Set it with the --config option or the ELMIGRATOR_CONFIG environment variable.',
            $configFile,
        ));
    }

    private function resolvePath(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('~^[a-zA-Z]:[/\\\\]~', $path) === 1;

        return $isAbsolute ? $path : getcwd() . DIRECTORY_SEPARATOR . $path;
    }
}
