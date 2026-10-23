<?php

/**
 * Loads a generated PHP file in a dedicated process and prints what it provides, as JSON.
 *
 * Generated files may raise compile-time fatal errors (e.g. incompatible method signatures) which
 * cannot be caught: loading them in a subprocess keeps the test runner alive.
 *
 * Usage: php inspect-php-file.php <file>
 */

declare(strict_types=1);

use Hyde1\EloquentMigrations\Migrations\Migration;
use Hyde1\EloquentMigrations\Seeds\Seeder;

require __DIR__ . '/../../vendor/autoload.php';

$declaredBefore = get_declared_classes();
$returned = require $argv[1];
$declared = array_diff(get_declared_classes(), $declaredBefore);

echo json_encode([
    'returns_migration' => $returned instanceof Migration,
    'declared_seeders' => array_values(array_filter($declared, fn (string $class): bool => is_subclass_of($class, Seeder::class))),
], JSON_THROW_ON_ERROR);
