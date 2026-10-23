<?php

declare(strict_types=1);

use Illuminate\Container\Container;

// Laravel defines app() in illuminate/foundation, which is not installed here. It was added for illuminate/console 9,
// whose migrator output called it unconditionally (View\Components\Mutators\EnsureRelativePaths); recent versions
// check it exists first. Kept as projects using the library may call it.
if (! function_exists('app')) {
    /**
     * Get the available container instance.
     *
     * @param array<string, mixed> $parameters
     */
    function app(?string $make = null, array $parameters = []): mixed
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}
