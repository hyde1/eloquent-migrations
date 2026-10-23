<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use InvalidArgumentException;
use Hyde1\EloquentMigrations\Support\TemplateWriter;

#[AsCommand('init')]
class Init extends Command
{
    protected function configure(): void
    {
        $this
            ->setDescription('Initialize the project')
            ->addArgument('path', InputArgument::REQUIRED, 'The path for the root directory of the project.')
            ->setHelp('Initialize the project for Eloquent Migrations' . PHP_EOL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $path = $this->createPath($input, $output);
        $this->createConfig($path, $output);
        $this->createBin($output);

        return self::SUCCESS;
    }

    protected function createPath(InputInterface $input, OutputInterface $output): string
    {
        $path = (string) $input->getArgument('path');

        $created = false;
        if (!is_dir($path)) {
            if (!mkdir($path)) {
                throw new InvalidArgumentException(sprintf(
                    'Cannot create `%s` directory',
                    $path,
                ));
            }
            $created = true;
        }

        if (!is_writable($path)) {
            throw new InvalidArgumentException(sprintf(
                'The directory `%s` is not writable',
                $path,
            ));
        }

        if ($created) {
            $output->writeln("<info>created</info> $path");
        }
        return $path;
    }

    protected function createConfig(string $path, OutputInterface $output): void
    {
        $outputPath = $path . DIRECTORY_SEPARATOR . 'elmigrator.php';
        new TemplateWriter()->write('data/elmigrator.php.dist', $outputPath);

        $output->writeln("<info>created</info> $outputPath");
    }

    public function createBin(OutputInterface $output): void
    {
        $outputPathBinFile = getcwd() . DIRECTORY_SEPARATOR . 'migrator';
        // The library binary, loading the application from the vendor directory instead of its own one
        new TemplateWriter()->write('bin/elmigrator', $outputPathBinFile, [
            "__DIR__ . '/../" => "'vendor/hyde1/eloquent-migrations/",
        ]);
        if (!chmod($outputPathBinFile, 0o755)) {
            // The binary is written, init keeps succeeding: it can still be run with php
            $output->writeln("<comment>Could not make $outputPathBinFile executable, run chmod +x on it</comment>");
        }

        $output->writeln("<info>Created</info> $outputPathBinFile");
    }
}
