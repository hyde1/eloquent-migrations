<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit;

use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\TestCase;

/**
 * data/elmigrator.php.dist is copied by the init command and is the reference for project config files.
 */
final class ConfigTemplateTest extends TestCase
{
    private const string TEMPLATE_FILE = __DIR__ . '/../../data/elmigrator.php.dist';

    /**
     * The template builds a Capsule manager, the documented way to use Eloquent outside of Laravel.
     * Checks it still evaluates without any connection and returns every key read by the commands.
     */
    public function testTemplateReturnsAValidConfiguration(): void
    {
        // When
        $config = require self::TEMPLATE_FILE;

        // Then
        $this->assertIsArray($config);
        $this->assertSame('el_migrations', $config['migration_table']);
        $this->assertSame(['migrations' => 'database/migrations', 'seeds' => 'database/seeders'], $config['paths']);
        $this->assertIsString($config['default_environment']);
        $this->assertInstanceOf(DatabaseManager::class, $config['db']);
        $this->assertSame('default', $config['db']->getDefaultConnection());
    }
}
