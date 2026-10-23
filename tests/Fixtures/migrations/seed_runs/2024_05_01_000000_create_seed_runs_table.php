<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema()->create('seed_runs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('name');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('seed_runs');
    }
};
