<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('migrate:install')]
class MigrateInstall extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setDescription('Create the migration repository')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
            ->setHelp('Create the migration repository' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (! $this->confirmToProceed()) {
            return self::SUCCESS;
        }

        $this->createMigrationRepository()->createRepository();

        $this->output->writeln('<info>Migration table created successfully.</info>');

        return self::SUCCESS;
    }
}
