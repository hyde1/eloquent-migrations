<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('create:database')]
class CreateDatabase extends AbstractCommand
{
    protected function configure(): void
    {
        $this
            ->setDescription('Create a database')
            ->addArgument('name', InputArgument::REQUIRED, 'The database name')
            ->setHelp('Creates a database' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbName = trim($input->getArgument('name'));
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $dbName)) {
            $output->writeln('<error>Invalid database name</error>');
            return self::INVALID;
        }
        $connection = $this->getDb()->connection($this->database);
        // Plain identifiers stay unquoted: PostgreSQL folds them to lower case, quoting them would change the name
        $sqlName = preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $dbName) ? $dbName : $connection->getQueryGrammar()->wrap($dbName);
        $connection->statement('CREATE DATABASE ' . $sqlName);

        $output->writeln(sprintf('<info>Database `%s` created successfully</info>', $dbName));

        return self::SUCCESS;
    }
}
