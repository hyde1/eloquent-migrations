<?php

use Hyde1\EloquentMigrations\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class () extends Migration {
    public function up(): void
    {
        $this->schema()->create('posts', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->string('title');
            $table->text('body');
            $table->decimal('price', 8, 2)->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->date('published_on')->nullable();
            $table->timestampsTz();

            $table->index('title');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        $this->schema()->dropIfExists('posts');
    }
};
