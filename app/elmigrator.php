<?php

// The project autoloader comes before the library's own one: loading both would register two copies of the classes
$files = [
    __DIR__ . '/../autoload.php',
    __DIR__ . '/../../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

$autoloader = array_find($files, fn (string $file): bool => is_file($file));
if ($autoloader === null) {
    fwrite(STDERR, 'Could not find the Composer autoloader, run composer install' . PHP_EOL);
    exit(1);
}
require_once $autoloader;

use Symfony\Component\Console\Application;
use Hyde1\EloquentMigrations\Command;

$app = new Application();

$app->addCommands([
    new Command\Init(),
    new Command\MigrateInstall(),
    new Command\CreateMigration(),
    new Command\Migrate(),
    new Command\FreshMigration(),
    new Command\Rollback(),
    new Command\Status(),
    new Command\CreateSeed(),
    new Command\RunSeed(),
    new Command\CreateDatabase(),
]);

return $app;
