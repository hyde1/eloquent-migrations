<?php

namespace Hyde1\EloquentMigrations\Command;

use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Hyde1\EloquentMigrations\Migrations\Migrator;
use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Filesystem\Filesystem;

class Migrate extends AbstractCommand
{
    protected static $defaultName = 'migrate';

    /**
     * The migration creator instance.
     *
     * @var \Illuminate\Database\Migrations\Migrator
     */
    protected \Illuminate\Database\Migrations\Migrator $migrator;

    /**
     * The migration repository
     *
     * @var DatabaseMigrationRepository
     */
    protected DatabaseMigrationRepository $repository;

    protected function configure()
    {
        $this
            ->setDescription('Run migrations')
            ->addOption('dry-run', 'x', InputOption::VALUE_NONE, 'Dump query to standard output instead of executing it')
            ->addOption('step', 's', InputOption::VALUE_REQUIRED, 'Force the migrations to be run so they can be rolled back individually', 1)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
            ->setHelp('Runs all available migrations' . PHP_EOL);

        parent::configure();
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->bootstrap($input, $output);

        if (! $this->confirmToProceed()) {
            return 0;
        }

        $this->repository = new DatabaseMigrationRepository($this->getDb(), $this->getMigrationTable());
        $this->migrator = new Migrator($this->repository, $this->getDb(), new Filesystem());

        if (! $this->migrator->repositoryExists()) {
            $this->repository->createRepository();
            $output->writeln('<info>Migration table created successfully.</info>');
        }

        $this->migrator->setOutput(new OutputStyle($input, $output))
            ->run([$this->getMigrationPath()], [
                'pretend' => $this->input->getOption('dry-run'),
                'step' => (int)$this->input->getOption('step'),
            ]);

        return 0;
    }
}
