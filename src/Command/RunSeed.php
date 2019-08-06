<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Eloquent\Model;
use Hyde1\EloquentMigrations\Seeds\Seeder;

class RunSeed extends AbstractCommand
{
	protected static $defaultName = 'seed:run';

	protected function configure()
	{
		$this
			->setDescription('Run seed')
			->addOption('--seed', '-s', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What is the name of the seeder?')
			->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
			->setHelp('Run all (or specified) seeders'.PHP_EOL);

		parent::configure();
	}

	public function execute(InputInterface $input, OutputInterface $output)
	{
		$this->bootstrap($input, $output);
		if (! $this->confirmToProceed()) {
            return;
        }

		$this->getDb();

		$start = microtime(true);

		$seedSet = $input->getOption('seed');
		Model::unguarded(function () use ($seedSet) {
			if (empty($seedSet)) {
				$this->runSeed();
			} else {
				foreach ($seedSet as $seed) {
					$this->runSeed($seed);
				}
			}
        });

		$end = microtime(true);
        $output->writeln('');
        $output->writeln('<comment>All seed completed. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
	}

    protected function runSeed($seed = null)
    {
		$seeds = $this->getSeeds();

		if ($seed === null) {
			// run all seeders
			foreach ($seeds as $seeder) {
				if (array_key_exists($seeder->getName(), $seeds)) {
					$this->executeSeed($seeder);
				}
			}
		} else {
			// run only one seeder
			if (array_key_exists($seed, $seeds)) {
				$this->executeSeed($seeds[$seed]);
			} else {
				throw new \InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
			}
		}
    }

	protected function executeSeed(Seeder $seeder)
	{
		$db = $this->getDb();
		$seeder->setDb($db);
		$this->output->writeln("<info>".$seeder->getName()."</info> seeding");
		$start = microtime(true);
		if (!$seeder->withinTransaction) {
			$seeder->run();
		} else {
			$db->transaction(function () use ($seeder) {
				$seeder->run();
			});
		}
		$end = microtime(true);
		$this->output->writeln("<info>".$seeder->getName()."</info> seeded".sprintf('%.4fs', $end - $start));
	}

	private $seeds = null;
	private function getSeeds()
	{
		if ($this->seeds === null) {
			$seeds = [];
			$files = $this->getSeedFiles();
			foreach ($files as $file) {
				$className = pathinfo($file, PATHINFO_FILENAME);

				require_once $file;

				if (!class_exists($className)) {
					throw new \InvalidArgumentException(sprintf(
						'Could not find class "%s" in file "%s"',
						$className,
						$file
					));
				}

				$seed = new $className;
				if (!($seed instanceof Seeder)) {
					throw new \InvalidArgumentException(sprintf(
						'The class "%s" in file "%s" must extend \Hyde1\EloquentMigrations\Seeds\Seeder',
						$className,
						$file
					));
				}
				$seeds[$className] = $seed;
			}

			ksort($seeds);
			$this->seeds = $seeds;
		}

		$this->seeds = $this->orderSeedsByDependencies($this->seeds);

		return $this->seeds;
	}

	private function getSeedFiles():array
	{
		return glob($this->getSeedPath() . DIRECTORY_SEPARATOR . '*.php');
	}

	private function orderSeedsByDependencies(array $seeds)
	{
		$orderedSeeds = [];
		foreach ($seeds as $seed) {
			$key = get_class($seed);
			$dependencies = $this->getSeedDependenciesInstances($seed);
			if (!empty($dependencies)) {
				$orderedSeeds[$key] = $seed;
				$orderedSeeds = array_merge($this->orderSeedsByDependencies($dependencies), $orderedSeeds);
			} else {
				$orderedSeeds[$key] = $seed;
			}
		}

		return $orderedSeeds;
	}

	private function getSeedDependenciesInstances(Seeder $seed)
	{
		$dependenciesInstances = [];

		$dependencies = $seed->getDependencies();
		if (!empty($dependencies)) {
			foreach ($dependencies as $dependency) {
				foreach ($this->seeds as $seed) {
					if (get_class($seed) === $dependency) {
						$dependenciesInstances[get_class($seed)] = $seed;
					}
				}
			}
		}

		return $dependenciesInstances;
	}

}
