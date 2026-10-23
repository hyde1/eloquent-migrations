<?php

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Eloquent\Model;
use Hyde1\EloquentMigrations\Seeds\Seeder;

#[AsCommand('seed:run')]
class RunSeed extends AbstractCommand
{
    public const SEEDERS_NAMESPACE = 'Database\\Seeders\\';

    protected function configure(): void
    {
        $this
            ->setDescription('Run seed')
            ->addOption('seed', 's', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'What is the name of the seeder?')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force the operation to run when in production')
            ->setHelp('Run all (or specified) seeders' . PHP_EOL);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap($input, $output);
        if (!$this->confirmToProceed()) {
            return self::FAILURE;
        }

        $this->getDb();

        $start = microtime(true);

        $seedSet = $input->getOption('seed');
        Model::unguarded(function () use ($seedSet): void {
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

        return self::SUCCESS;
    }

    protected function runSeed(?string $seed = null): void
    {
        $seeds = $this->getSeeds();
        if ($seed !== null && !str_contains($seed, '\\')) {
            // Short class name, seeders are declared in a single namespace
            $seed = self::SEEDERS_NAMESPACE . $seed;
        }

        if ($seed === null) {
            // run all seeders
            foreach ($seeds as $seeder) {
                if (array_key_exists($seeder->getName(), $seeds)) {
                    $this->executeSeed($seeder);
                }
            }
        } elseif (array_key_exists($seed, $seeds)) {
            $this->executeSeed($seeds[$seed]);
        } else {
            throw new \InvalidArgumentException(sprintf('The seed class "%s" does not exist', $seed));
        }
    }

    protected function executeSeed(Seeder $seeder): void
    {
        $db = $this->getDb()->connection($this->database);
        $seeder->setDb($db);
        $this->output->writeln("<info>" . $seeder->getName() . "</info> seeding");
        $start = microtime(true);
        if (!$seeder->withinTransaction) {
            $seeder->run();
        } else {
            $db->transaction(function () use ($seeder): void {
                $seeder->run();
            });
        }
        $end = microtime(true);
        $this->output->writeln("<info>" . $seeder->getName() . "</info> seeded" . sprintf('%.4fs', $end - $start));
    }

    /** @var array<string, Seeder>|null Seeders indexed by class name */
    private ?array $seeds = null;

    /**
     * @return array<string, Seeder>
     */
    private function getSeeds(): array
    {
        if ($this->seeds === null) {
            $seeds = [];
            $files = $this->getSeedFiles();
            foreach ($files as $file) {
                $className = pathinfo($file, PATHINFO_FILENAME);
                $className = self::SEEDERS_NAMESPACE . $className;

                require_once $file;

                if (!class_exists($className)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Could not find class "%s" in file "%s"',
                        $className,
                        $file,
                    ));
                }

                $seed = new $className();
                if (!($seed instanceof Seeder)) {
                    throw new \InvalidArgumentException(sprintf(
                        'The class "%s" in file "%s" must extend \Hyde1\EloquentMigrations\Seeds\Seeder',
                        $className,
                        $file,
                    ));
                }
                $seeds[$className] = $seed;
            }

            ksort($seeds);
            $this->warnAboutUnknownDependencies($seeds);
            // getSeedDependenciesInstances() reads the loaded seeders while they are ordered
            $this->seeds = $seeds;
            try {
                $this->seeds = $this->orderSeedsByDependencies($seeds);
            } catch (\InvalidArgumentException $exception) {
                // Next runs of this command instance must reject the cycle again
                $this->seeds = null;
                throw $exception;
            }
        }

        return $this->seeds;
    }

    /**
     * Dependencies are ordered among the seeders of the seeds directory, the other ones are ignored.
     *
     * @param array<string, Seeder> $seeds
     */
    private function warnAboutUnknownDependencies(array $seeds): void
    {
        foreach ($seeds as $seed) {
            foreach ($seed->getDependencies() as $dependency) {
                if (!array_key_exists($dependency, $seeds)) {
                    $this->output->writeln(sprintf(
                        '<comment>The seeder "%s" depends on "%s", which is not in the seeds directory: this dependency is ignored</comment>',
                        $seed->getName(),
                        $dependency,
                    ));
                }
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getSeedFiles(): array
    {
        return glob($this->getSeedPath() . DIRECTORY_SEPARATOR . '*.php') ?: [];
    }

    /**
     * @param array<string, Seeder> $seeds
     * @param list<string> $dependents Seeders whose dependencies are being ordered, to detect circular dependencies
     * @return array<string, Seeder>
     */
    private function orderSeedsByDependencies(array $seeds, array $dependents = []): array
    {
        $orderedSeeds = [];
        foreach ($seeds as $seed) {
            $key = get_class($seed);
            if (in_array($key, $dependents, true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Circular dependency between seeders: %s',
                    implode(' -> ', [...$dependents, $key]),
                ));
            }
            $dependencies = $this->getSeedDependenciesInstances($seed);
            $orderedSeeds[$key] = $seed;
            if (!empty($dependencies)) {
                $orderedSeeds = array_merge($this->orderSeedsByDependencies($dependencies, [...$dependents, $key]), $orderedSeeds);
            }
        }

        return $orderedSeeds;
    }

    /**
     * @return array<string, Seeder>
     */
    private function getSeedDependenciesInstances(Seeder $seed): array
    {
        $dependenciesInstances = [];

        $dependencies = $seed->getDependencies();
        if (!empty($dependencies)) {
            foreach ($dependencies as $dependency) {
                foreach ($this->seeds ?? [] as $seed) {
                    if (get_class($seed) === $dependency) {
                        $dependenciesInstances[get_class($seed)] = $seed;
                    }
                }
            }
        }

        return $dependenciesInstances;
    }
}
