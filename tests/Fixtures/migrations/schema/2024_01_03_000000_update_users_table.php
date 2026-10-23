<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema()->table('users', function (Blueprint $table): void {
            $table->string('phone')->nullable();
            $table->renameColumn('name', 'full_name');
        });
        $this->schema()->table('users', function (Blueprint $table): void {
            $table->dropColumn('age');
        });
    }

    public function down(): void
    {
        $this->schema()->table('users', function (Blueprint $table): void {
            $table->integer('age')->nullable();
            $table->renameColumn('full_name', 'name');
        });
        $this->schema()->table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });
    }
};
