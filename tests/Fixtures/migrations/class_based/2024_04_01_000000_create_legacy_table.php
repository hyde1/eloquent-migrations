<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateLegacyTable extends Migration
{
    protected $connection = 'secondary';

    public function up(): void
    {
        $this->schema()->create('legacy', function (Blueprint $table): void {
            $table->increments('id');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('legacy');
    }
}
