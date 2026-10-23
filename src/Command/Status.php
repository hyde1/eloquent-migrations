<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Support\Collection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator as BaseMigrator;

#[AsCommand('status')]
class Status extends AbstractCommand
{
    protected BaseMigrator $migrator;
    protected DatabaseMigrationRepository $repository;

    protected function configure(): void
    {
        $this
            ->setDescription('Display migration status')
            ->setHelp('Show the status of each migration' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->repository = $this->createMigrationRepository();
        $this->migrator = $this->createMigrator($this->repository);

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
     * @param array<mixed> $ran
     * @param array<string, int> $batches
     * @return Collection<string, list<int|string>>
     */
    protected function getStatusFor(array $ran, array $batches): Collection
    {
        return Collection::make($this->getAllMigrationFiles())
            ->map(function (string $migration) use ($ran, $batches): array {
                $migrationName = $this->migrator->getMigrationName($migration);

                return in_array($migrationName, $ran)
                    ? ['<info>Yes</info>', $migrationName, $batches[$migrationName]]
                    : ['<fg=red>No</fg=red>', $migrationName];
            });
    }

    /**
     * Get an array of all the migration files.
     *
     * @return array<string, string> Paths indexed by migration name
     */
    protected function getAllMigrationFiles(): array
    {
        return $this->migrator->getMigrationFiles([$this->getMigrationPath()]);
    }
}
