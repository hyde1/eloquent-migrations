<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Database\Console\Migrations\TableGuesser;
use Illuminate\Database\Migrations\MigrationCreator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

#[AsCommand('create')]
class CreateMigration extends AbstractCommand
{
    protected MigrationCreator $creator;

    protected function configure()
    {
        $this
            ->setDescription('Create a new migration')
            ->addArgument('name', InputArgument::REQUIRED, 'The migration name')
            ->addOption('--create', null, InputOption::VALUE_REQUIRED, 'The table to create')
            ->addOption('--table', null, InputOption::VALUE_REQUIRED, 'The table to migrate')
            ->setHelp('Creates a new migration' . PHP_EOL);

        parent::configure();

        $this->creator = new MigrationCreator(new Filesystem(), __DIR__ . '/../../data/stubs');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
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
            [$table, $create] = TableGuesser::guess($name);
        }

        $this->writeMigration($name, $table, $create);

        return 0;
    }

    /**
     * Write the migration file to disk.
     *
     * @param string $name
     * @param string $table
     * @param bool $create
     * @throws \Exception
     */
    protected function writeMigration(string $name, string $table, bool $create)
    {
        $file = $this->creator->create(
            $name,
            $this->getMigrationPath(),
            $table,
            $create
        );
        $this->output->writeln("<info>Created Migration:</info> {$file}");
    }
}
