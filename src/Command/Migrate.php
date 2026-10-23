<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Illuminate\Filesystem\Filesystem;

#[AsCommand('migrate')]
class Migrate extends AbstractCommand
{
    protected BaseMigrator $migrator;
    protected DatabaseMigrationRepository $repository;

    protected function configure(): void
    {
        $this
            ->setDescription('Run migrations')
            ->addOption('dry-run', 'x', InputOption::VALUE_NONE, 'Dump query to standard output instead of executing it')
            ->addOption('step', 's', InputOption::VALUE_REQUIRED, 'Force the migrations to be run so they can be rolled back individually', 1)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
            ->setHelp('Runs all available migrations' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);

        if (! $this->confirmToProceed()) {
            return self::SUCCESS;
        }

        $this->repository = new DatabaseMigrationRepository($this->getDb(), $this->getMigrationTable());
        $this->migrator = new Migrator($this->repository, $this->getDb(), new Filesystem());
        $this->migrator->setOutput($output);

        $this->migrator->usingConnection($this->getConnectionName(), function () use ($output, $input): void {
            $this->prepareDatabase();

            // Next, we will check to see if a path option has been defined. If it has
            // we will use the path relative to the root of this installation folder
            // so that migrations may be run for any path within the applications.
            $this->migrator->setOutput(new OutputStyle($input, $output))
                ->run([$this->getMigrationPath()], [
                    'pretend' => $this->input->getOption('dry-run'),
                    'step' => (int) $this->input->getOption('step'),
                ]);
        });

        return self::SUCCESS;
    }

    /**
     * Prepare the migration database for running.
     */
    protected function prepareDatabase(): void
    {
        if (! $this->migrator->repositoryExists()) {
            // Running the migrations was already confirmed
            $this->call('migrate:install', array_filter([
                '--database' => $this->database,
                '--force' => true,
            ]));
        }
    }
}
