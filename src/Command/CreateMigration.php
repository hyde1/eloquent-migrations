<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Hyde1\EloquentMigrations\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Composer;

class CreateMigration extends AbstractCommand
{
	protected static $defaultName = 'create';

	/**
     * The migration creator instance.
     *
     * @var \Illuminate\Database\Migrations\MigrationCreator
     */
    protected $creator;

	protected function configure()
	{
		$this
			->setDescription('Create a new migration')
			->addArgument('name', InputArgument::REQUIRED, 'The migration name')
			->addOption('--create', null, InputOption::VALUE_REQUIRED, 'The table to create')
			->addOption('--table', null, InputOption::VALUE_REQUIRED, 'The table to migrate')
			->setHelp('Creates a new migration'.PHP_EOL);

		parent::configure();

		$this->creator = new MigrationCreator(new Filesystem);
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->bootstrap($input, $output);

		$name = Str::snake(trim($this->input->getArgument('name')));
		$table = $this->input->getOption('table');
		$create = $this->input->getOption('create') ?: false;
		if (! $table && is_string($create)) {
			$table = $create;
			$create = true;
		}
        if (! $table) {
			[$table, $create] = \Illuminate\Database\Console\Migrations\TableGuesser::guess($name);
		}

		$this->writeMigration($name, $table, $create);
	}

	/**
	 * Write the migration file to disk.
	 *
	 * @param  string  $name
	 * @param  string  $table
	 * @param  bool    $create
	 * @return string
	*/
	protected function writeMigration($name, $table, $create)
	{
		$file = $this->creator->create(
			$name, $this->getMigrationPath(), $table, $create
		);
		$this->output->writeln("<info>Created Migration:</info> {$file}");
	}
}
