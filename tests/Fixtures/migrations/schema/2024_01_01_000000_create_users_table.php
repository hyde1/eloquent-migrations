<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema()->create('users', function (Blueprint $table): void {
            $table->uuid('id');
            $table->string('email')->unique();
            $table->string('name');
            $table->boolean('active')->default(true);
            $table->integer('age')->nullable();
            $table->json('settings')->nullable();
            $table->dateTimeTz('last_login_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->primary('id');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('users');
    }
};
