<?php

$files = [
	__DIR__ . '/../autoload.php',
	__DIR__ . '/../../../autoload.php',
	__DIR__ . '/../vendor/autoload.php',
];

foreach ($files as $file) {
	if (is_file($file)) {
		require_once $file;
	}
}

use Symfony\Component\Console\Application;
use Hyde1\EloquentMigrations\Command;

$app = new Application();

$app->addCommands([
	new Command\Init(),
	new Command\CreateMigration(),
	new Command\Migrate(),
	new Command\Rollback(),
	new Command\Status(),
	new Command\CreateSeed(),
	new Command\RunSeed(),
]);

return $app;
