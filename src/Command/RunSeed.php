<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Illuminate\Database\Eloquent\Model;
use Hyde1\EloquentMigrations\Seeds\Seeder;
use Hyde1\EloquentMigrations\Seeds\SeederLoader;
use Hyde1\EloquentMigrations\Seeds\SeederSorter;

#[AsCommand('seed:run')]
class RunSeed extends AbstractCommand
{
    public const SEEDERS_NAMESPACE = SeederLoader::NAMESPACE;

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
                $this->executeSeed($seeder);
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

    /** @var array<string, Seeder>|null Seeders indexed by class name, in execution order */
    private ?array $seeds = null;

    /**
     * @return array<string, Seeder>
     */
    private function getSeeds(): array
    {
        if ($this->seeds === null) {
            $seeds = new SeederLoader()->load($this->getSeedPath());
            $sorter = new SeederSorter();
            foreach ($sorter->findUnknownDependencies($seeds) as [$seed, $dependency]) {
                $this->output->writeln(sprintf(
                    '<comment>The seeder "%s" depends on "%s", which is not in the seeds directory: this dependency is ignored</comment>',
                    $seed,
                    $dependency,
                ));
            }
            // Only cached once ordered: a next run of this command instance rejects a circular dependency again
            $this->seeds = $sorter->sort($seeds);
        }

        return $this->seeds;
    }
}
