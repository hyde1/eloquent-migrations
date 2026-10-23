<?php

declare(strict_types=1);

namespace Hyde1\EloquentMigrations\Tests\Support\Doubles;

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;

/**
 * Concrete migration exposing the protected helpers offered to project migrations.
 */
final class ExposedMigration extends Migration
{
    public function up(): void {}

    public function down(): void {}

    public function exposedDb(): Connection
    {
        return $this->db();
    }

    public function exposedSchema(): Builder
    {
        return $this->schema();
    }
}
