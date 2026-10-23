<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Unit;

use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Some Illuminate components (console view components used by the Migrator output) call the
 * Laravel app() helper, which does not exist outside of a Laravel application.
 */
final class HelpersTest extends TestCase
{
    public function testAppReturnsTheContainerInstance(): void
    {
        $this->assertSame(Container::getInstance(), app());
    }

    public function testAppResolvesAnAbstractFromTheContainer(): void
    {
        // Given
        Container::getInstance()->bind('elmigrator.test-binding', fn (Container $container, array $parameters): string => 'resolved-' . ($parameters['suffix'] ?? ''));

        // When
        $resolved = app('elmigrator.test-binding', ['suffix' => 'value']);

        // Then
        $this->assertSame('resolved-value', $resolved);
    }
}
