<?php

use Hyde1\EloquentMigrations\Migrations\Migration;

return new class () extends Migration {
    public $withinTransaction = false;

    public function up(): void
    {
        $this->db()->table('users')->insert([
            'id' => '00000000-0000-0000-0000-000000000001',
            'email' => 'admin@example.com',
            'full_name' => 'Admin',
        ]);
    }

    public function down(): void
    {
        $this->db()->table('users')->where('email', 'admin@example.com')->delete();
    }
};
