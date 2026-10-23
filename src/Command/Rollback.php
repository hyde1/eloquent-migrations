<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Console\OutputStyle;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator as BaseMigrator;
use Illuminate\Filesystem\Filesystem;

#[AsCommand('rollback')]
class Rollback extends AbstractCommand
{
    protected BaseMigrator $migrator;
    protected DatabaseMigrationRepository $repository;

    protected function configure(): void
    {
        $this
            ->setDescription('Rollback migrations')
            ->addOption('dry-run', 'x', InputOption::VALUE_NONE, 'Dump query to standard output instead of executing it')
            ->addOption('step', 's', InputOption::VALUE_REQUIRED, 'Number of migrations to rollback', 0)
            ->addOption('migration', 'm', InputOption::VALUE_REQUIRED, 'Rollback a specific migration (file name without extension), whatever its batch')
            ->setHelp('Rollback the last migration' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);
        $this->repository = new DatabaseMigrationRepository($this->getDb(), $this->getMigrationTable());
        $this->migrator = new Migrator($this->repository, $this->getDb(), new Filesystem());
        $this->migrator->setOutput($output);

        $this->migrator->usingConnection($this->getConnectionName(), function () use ($output, $input): void {
            $this->migrator
                ->setOutput(new OutputStyle($input, $output))
                ->rollback(
                    [$this->getMigrationPath()],
                    [
                        'pretend' => $this->input->getOption('dry-run'),
                        'step' => (int) $this->input->getOption('step'),
                        'migration' => $this->input->getOption('migration'),
                    ],
                );
        });

        return 0;
    }
}
