<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\ArrayInput;

abstract class AbstractCommand extends Command
{
    /** @var array */
    protected $config;

    /** @var InputInterface */
    protected $input;

    /** @var OutputInterface */
    protected $output;

    /** @var string */
    protected $environment;

    protected function configure()
    {
        $this->addOption('--config', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load', 'elmigrator.php');
        $this->addOption('--env', '-e', InputOption::VALUE_OPTIONAL, 'Choose an environment', null);
    }

    protected function bootstrap(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;
        $this->loadConfig($input);
    }

    protected function loadConfig(InputInterface $input)
    {
        $configfile = (string)$input->getOption('config');
        $this->config = require getcwd() . DIRECTORY_SEPARATOR . $configfile;
        $this->environment = $input->getOption('env') ?? $this->config['default_environment'];
    }

    protected function getMigrationPath(): string
    {
        return (string)$this->config['paths']['migrations'];
    }

    protected function getSeedPath(): string
    {
        return (string)$this->config['paths']['seeds'];
    }

    protected function getDb()
    {
        return $this->config['db'];
    }

    protected function environment()
    {
        return $this->environment;
    }

    protected function getMigrationTable(): string
    {
        return (string)$this->config['migration_table'];
    }

    protected function table($headers, $contents)
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)
            ->setRows($contents)
            ->render();
    }

    public function confirm($message)
    {
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion($message, false);

        if (!$helper->ask($this->input, $this->output, $question)) {
            return false;
        }
        return true;
    }

    /**
     * Confirm before proceeding with the action.
     *
     * This method only asks for confirmation in production.
     *
     * @param  string  $warning
     * @param  \Closure|bool|null  $callback
     * @return bool
     */
    public function confirmToProceed($warning = 'Application In Production!', $callback = null)
    {
        $callback = is_null($callback) ? $this->getDefaultConfirmCallback() : $callback;
        $shouldConfirm = $callback instanceof \Closure ? call_user_func($callback) : $callback;
        if ($shouldConfirm) {
            if ($this->input->hasOption('force') && $this->input->getOption('force')) {
                return true;
            }
            $this->output->writeln("<fg=yellow>$warning</>");
            $confirmed = $this->confirm('Do you really wish to run this command?');
            if (! $confirmed) {
                $this->output->writeln('<comment>Command Cancelled!</comment>');
                return false;
            }
        }
        return true;
    }

    /**
     * Get the default confirmation callback.
     *
     * @return \Closure
     */
    protected function getDefaultConfirmCallback()
    {
        return function () {
            return $this->environment() === 'production';
        };
    }

    /**
     * Call another console command.
     *
     * @param  string  $command
     * @param  array   $arguments
     * @return int
     */
    public function call($command, array $arguments = [])
    {
        $arguments['command'] = $command;
        return $this->getApplication()->find($command)->run(
            new ArrayInput($arguments), $this->output
        );
    }
}
