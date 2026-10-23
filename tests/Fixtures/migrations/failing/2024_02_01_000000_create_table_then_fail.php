<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema()->create('transactional_table', function (Blueprint $table): void {
            $table->increments('id');
        });

        throw new RuntimeException('Migration failed on purpose');
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('transactional_table');
    }
};
