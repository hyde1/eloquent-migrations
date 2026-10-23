<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit;

use Hyde1\EloquentMigrations\Command;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputOption;

/**
 * Wiring of the console application shipped in app/elmigrator.php and bin/elmigrator.
 */
final class ApplicationTest extends TestCase
{
    private const string APPLICATION_FILE = __DIR__ . '/../../app/elmigrator.php';
    private const string BIN_FILE = __DIR__ . '/../../bin/elmigrator';

    /**
     * @param class-string $class
     */
    #[DataProvider('commandsProvider')]
    public function testRegistersTheCommand(string $name, string $class): void
    {
        $this->assertInstanceOf($class, $this->makeApplication()->find($name));
    }

    /**
     * Every command reading the project configuration shares the same options, used by projects scripts and CI.
     * Checks their names, shortcuts and default value did not change.
     */
    #[DataProvider('configurableCommandsProvider')]
    public function testConfigurableCommandExposesTheCommonOptions(string $name): void
    {
        // When
        $definition = $this->makeApplication()->find($name)->getDefinition();

        // Then
        $config = $definition->getOption('config');
        $this->assertSame('c', $config->getShortcut());
        $this->assertTrue($config->isValueRequired());
        $this->assertSame('elmigrator.php', $config->getDefault());

        $this->assertSame('e', $definition->getOption('env')->getShortcut());
        $this->assertSame('d', $definition->getOption('database')->getShortcut());
    }

    /**
     * @param list<string> $expectedOptions
     */
    #[DataProvider('commandOptionsProvider')]
    public function testCommandExposesItsOptions(string $name, array $expectedOptions): void
    {
        // When
        $options = array_map(
            fn (InputOption $option): string => $option->getName(),
            $this->makeApplication()->find($name)->getDefinition()->getOptions(),
        );

        // Then
        foreach ($expectedOptions as $expectedOption) {
            $this->assertContains($expectedOption, $options);
        }
    }

    /**
     * Projects run the library through its binary (vendor/bin/elmigrator).
     * Checks the binary boots the application and lists every command.
     */
    public function testBinaryListsAllCommands(): void
    {
        // When
        exec(sprintf('%s %s list --raw 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(self::BIN_FILE)), $output, $exitCode);

        // Then
        $this->assertSame(0, $exitCode, implode(PHP_EOL, $output));
        $listedCommands = array_map(fn (string $line): string => (string) strtok($line, ' '), $output);
        foreach (self::commandsProvider() as [$name]) {
            $this->assertContains($name, $listedCommands);
        }
    }

    // -------------------
    // 🛠️ Helpers
    // -------------------

    private function makeApplication(): Application
    {
        $application = require self::APPLICATION_FILE;
        $this->assertInstanceOf(Application::class, $application);

        return $application;
    }

    // -------------------
    // 🧪 Data Providers
    // -------------------

    /** @return array<string, array{string, class-string}> */
    public static function commandsProvider(): array
    {
        return [
            'init' => ['init', Command\Init::class],
            'migrate:install' => ['migrate:install', Command\MigrateInstall::class],
            'create' => ['create', Command\CreateMigration::class],
            'migrate' => ['migrate', Command\Migrate::class],
            'migrate:fresh' => ['migrate:fresh', Command\FreshMigration::class],
            'rollback' => ['rollback', Command\Rollback::class],
            'status' => ['status', Command\Status::class],
            'seed:create' => ['seed:create', Command\CreateSeed::class],
            'seed:run' => ['seed:run', Command\RunSeed::class],
            'create:database' => ['create:database', Command\CreateDatabase::class],
        ];
    }

    /** @return array<string, array{string}> */
    public static function configurableCommandsProvider(): array
    {
        return array_map(
            fn (array $command): array => [$command[0]],
            array_filter(self::commandsProvider(), fn (array $command): bool => $command[0] !== 'init'),
        );
    }

    /** @return array<string, array{string, list<string>}> */
    public static function commandOptionsProvider(): array
    {
        return [
            'create' => ['create', ['create', 'table']],
            'migrate' => ['migrate', ['dry-run', 'step', 'force']],
            'migrate:install' => ['migrate:install', ['force']],
            'migrate:fresh' => ['migrate:fresh', ['drop-views', 'drop-types', 'force', 'seed', 'seeder']],
            'rollback' => ['rollback', ['dry-run', 'step', 'migration']],
            'seed:run' => ['seed:run', ['seed', 'force']],
        ];
    }
}
