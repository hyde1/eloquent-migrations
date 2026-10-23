<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    protected $connection = 'secondary';

    public function up(): void
    {
        $this->schema()->create('audit_logs', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('message');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('audit_logs');
    }
};
