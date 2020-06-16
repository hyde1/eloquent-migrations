<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Illuminate\Support\Composer;

class FreshMigration extends AbstractCommand
{
	protected static $defaultName = 'migrate:fresh';

	protected function configure()
	{
		$this
			->setDescription('Drop all tables and re-run all migrations')
			->addOption('database', '-d', InputOption::VALUE_OPTIONAL, 'The database connection to use')
			->addOption('drop-views', null, InputOption::VALUE_NONE, 'Drop all tables and views')
			->addOption('drop-types', null, InputOption::VALUE_NONE, 'Drop all tables and types (Postgres only)')
			->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
			->addOption('seed', null, InputOption::VALUE_NONE, 'Indicates if the seed task should be re-run')
			->addOption('seeder', null, InputOption::VALUE_OPTIONAL, 'Which root seeder should be usedWhich root seeder should be used??')
			->setHelp('Drop all tables and re-run all migrations'.PHP_EOL);

		parent::configure();
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->bootstrap($input, $output);

		if (! $this->confirmToProceed()) {
            return 1;
        }

		$database = $this->input->getOption('database');

		if ($this->input->getOption('drop-views')) {
			$this->dropAllViews($database);
			$this->output->writeln('<info>Dropped all views successfully.</info>');
		}

		$this->dropAllTables($database);
		$this->output->writeln('<info>Dropped all tables successfully.</info>');

		if ($this->input->getOption('drop-types')) {
			$this->dropAllTypes($database);
			$this->output->writeln('<info>Dropped all types successfully.</info>');
		}

		$this->call('migrate', array_filter([
			'--database' => $database,
			'--force' => true,
		]));

		if ($this->input->getOption('seed')) {
			$this->call('seed:run', array_filter([
				'--database' => $database,
				'--seed' => $this->input->getOption('seeder'),
				'--force' => true,
			]));
		}

		return 0;
	}

    /**
     * Drop all of the database tables.
     *
     * @param  string  $database
     * @return void
     */
    protected function dropAllTables($database)
    {
        $this->getDb()->connection($database)
			->getSchemaBuilder()
			->dropAllTables();
    }

	/**
     * Drop all of the database views.
     *
     * @param  string  $database
     * @return void
     */
    protected function dropAllViews($database)
    {
        $this->getDb()->connection($database)
                    ->getSchemaBuilder()
                    ->dropAllViews();
    }
    /**
     * Drop all of the database types.
     *
     * @param string $database
     * @return void
     */
    protected function dropAllTypes($database)
    {
        $this->getDb()->connection($database)
                    ->getSchemaBuilder()
                    ->dropAllTypes();
    }
}
