<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Database\Console\Migrations\TableGuesser;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('create:database')]
class CreateDatabase extends AbstractCommand
{
    protected function configure()
    {
        $this
            ->setDescription('Create a database')
            ->addArgument('name', InputArgument::REQUIRED, 'The database name')
            ->setHelp('Creates a database' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);
        $dbName = trim($input->getArgument('name'));
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
            $output->writeln('<error>Invalid database name</error>');
            return self::INVALID;
        }
        $this->getDb()->statement('CREATE DATABASE ' . $dbName);

        $output->writeln(sprintf('<info>Database `%s` created successfuly</info>', $dbName));

        return self::SUCCESS;
    }
}
