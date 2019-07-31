<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

abstract class AbstractCommand extends Command
{
	/** @var array */
	protected $config;

	/** @var InputInterface */
	protected $input;

	/** @var OutputInterface */
	protected $output;

	protected function configure()
	{
		$this->addOption('--config', '-c', InputOption::VALUE_REQUIRED, 'The configuration file to load', 'elmigrator.php');
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
	}

	protected function getMigrationPath():string
	{
		return (string)$this->config['paths']['migrations'];
	}

	protected function getSeedPath():string
	{
		return (string)$this->config['paths']['seeds'];
	}

	protected function getDb()
	{
		return $this->config['db'];
	}

	protected function getMigrationTable():string
	{
		return (string)$this->config['migration_table'];
	}

	protected function table($headers, $contents)
	{
		$table = new \Symfony\Component\Console\Helper\Table($this->output);
		$table->setHeaders($headers)
			->setRows($contents)
			->render();
	}
}
