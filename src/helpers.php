<?php

use Illuminate\Container\Container;

if (! function_exists('app')) {
    /**
     * Get the available container instance.
     */
    function app(?string $make = null, array $parameters = []): mixed
    {
        if (is_null($make)) {
            return Container::getInstance();
        }

        return Container::getInstance()->make($make, $parameters);
    }
}
