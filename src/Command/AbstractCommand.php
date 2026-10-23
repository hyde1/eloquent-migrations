<?php

namespace Hyde1\EloquentMigrations\Command;

use Closure;
use Illuminate\Database\DatabaseManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\ArrayInput;

abstract class AbstractCommand extends Command
{
    protected string $configFile;
    /** @var array<string, mixed> */
    protected array $config;
    protected InputInterface $input;
    protected OutputInterface $output;
    protected string $environment;
    protected ?string $database;

    protected function configure(): void
    {
        $this->addOption('config', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load', 'elmigrator.php');
        $this->addOption('env', '-e', InputOption::VALUE_OPTIONAL, 'Choose an environment');
        $this->addOption('database', '-d', InputOption::VALUE_OPTIONAL, 'The database connection to use');
    }

    protected function bootstrap(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->loadConfig($input);
    }

    protected function loadConfig(InputInterface $input): void
    {
        $this->configFile = (string) $input->getOption('config');
        $this->config = $this->requireConfig();
        $this->environment = $input->getOption('env') ?? $this->config['default_environment'];
        $this->database = $input->getOption('database') ?? $this->config['database'] ?? null;
    }

    /**
     * Load the configuration file given with --config, or else the one from the ELMIGRATOR_CONFIG environment variable.
     *
     * @return array<string, mixed>
     */
    private function requireConfig(): array
    {
        $configFiles = [getcwd() . DIRECTORY_SEPARATOR . $this->configFile];
        if (isset($_ENV['ELMIGRATOR_CONFIG'])) {
            $configFiles[] = getcwd() . DIRECTORY_SEPARATOR . $_ENV['ELMIGRATOR_CONFIG'];
        }

        foreach ($configFiles as $configFile) {
            if (!file_exists($configFile)) {
                continue;
            }

            $config = require $configFile;
            if (!is_array($config)) {
                throw new \RuntimeException(sprintf('The configuration file "%s" must return an array', $configFile));
            }

            return $config;
        }

        throw new \RuntimeException(sprintf(
            'Could not find the configuration file "%s". Set it with the --config option or the ELMIGRATOR_CONFIG environment variable.',
            $this->configFile,
        ));
    }

    protected function getMigrationPath(): string
    {
        return (string) $this->config['paths']['migrations'];
    }

    protected function getSeedPath(): string
    {
        return (string) $this->config['paths']['seeds'];
    }

    protected function getDb(): DatabaseManager
    {
        return $this->config['db'];
    }

    protected function environment(): string
    {
        return $this->environment;
    }

    /**
     * Name of the connection selected with --database (or in the configuration), else the default one.
     */
    protected function getConnectionName(): string
    {
        return $this->database ?? $this->getDb()->getDefaultConnection();
    }

    protected function getMigrationTable(): string
    {
        return (string) $this->config['migration_table'];
    }

    /**
     * @param list<string> $headers
     * @param array<array<mixed>> $contents
     */
    protected function table(array $headers, array $contents): void
    {
        $table = new Table($this->output);
        $table->setHeaders($headers)
            ->setRows($contents)
            ->render();
    }

    public function confirm(string $message): bool
    {
        $helper = $this->getHelper('question');
        if (!$helper instanceof QuestionHelper) {
            throw new \LogicException('The question helper is not available');
        }
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
     */
    public function confirmToProceed(string $warning = 'Application In Production!', Closure|bool|null $callback = null): bool
    {
        $callback = is_null($callback) ? $this->getDefaultConfirmCallback() : $callback;
        $shouldConfirm = $callback instanceof Closure ? call_user_func($callback) : $callback;
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
     */
    protected function getDefaultConfirmCallback(): Closure
    {
        return function (): bool {
            return $this->environment() === 'production';
        };
    }

    /**
     * Call another console command.
     *
     * @param array<string, mixed> $arguments
     */
    public function call(string $command, array $arguments = []): int
    {
        $application = $this->getApplication();
        if ($application === null) {
            throw new \LogicException('The command is not registered in an application');
        }

        $arguments['command'] = $command;
        $arguments['--config'] = $this->configFile;
        return $application->find($command)->run(
            new ArrayInput($arguments),
            $this->output,
        );
    }
}
