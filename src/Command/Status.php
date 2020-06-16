<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Support\Collection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Filesystem\Filesystem;

class Status extends AbstractCommand
{
	protected static $defaultName = 'status';

	/**
     * The migration creator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected $migrator;

	/**
	 * The migration repository
	 *
	 * @var DatabaseMigrationRepository
	 */
	protected $repository;

	protected function configure()
	{
		$this
			->setDescription('Display migration status')
			->setHelp('Show the status of each migration'.PHP_EOL);

		parent::configure();
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->bootstrap($input, $output);
		$this->repository = new DatabaseMigrationRepository($this->getDb(), $this->getMigrationTable());
		$this->migrator = new Migrator($this->repository, $this->getDb(), new Filesystem);

		if (! $this->migrator->repositoryExists()) {
			throw new \RuntimeException('The migration table is not installed');
		}

		$ran = $this->migrator->getRepository()->getRan();
		$batches = $this->migrator->getRepository()->getMigrationBatches();
		if (count($migrations = $this->getStatusFor($ran, $batches)) > 0) {
			$this->table(['Ran?', 'Migration', 'Batch'], $migrations->toArray());
		} else {
			$output->writeln('<error>No migrations found</error>');
		}

		return 0;
	}

	/**
	 * Get the status for the given ran migrations.
	 *
	 * @param  array  $ran
	 * @param  array  $batches
	 * @return Collection
	 */
	protected function getStatusFor(array $ran, array $batches)
	{
		return Collection::make($this->getAllMigrationFiles())
			->map(function ($migration) use ($ran, $batches) {
				$migrationName = $this->migrator->getMigrationName($migration);

				return in_array($migrationName, $ran)
					? ['<info>Yes</info>', $migrationName, $batches[$migrationName]]
					: ['<fg=red>No</fg=red>', $migrationName];
			});
	}

	/**
	 * Get an array of all of the migration files.
	 *
	 * @return array
	 */
	protected function getAllMigrationFiles()
	{
		return $this->migrator->getMigrationFiles([$this->getMigrationPath()]);
	}
}
