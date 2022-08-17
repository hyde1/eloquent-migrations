<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Database\Console\Migrations\TableGuesser;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CreateDatabase extends AbstractCommand
{
    protected static $defaultName = 'create:database';

    protected function configure()
    {
        $this
            ->setDescription('Create a database')
            ->addArgument('name', InputArgument::REQUIRED, 'The database name')
            ->setHelp('Creates a database' . PHP_EOL);

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);
        $this->getDb()->statement('CREATE DATABASE :database', ['database' => $input->getArgument('name')]);

        return 0;
    }
}
