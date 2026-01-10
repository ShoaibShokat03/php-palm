<?php

use App\Database\Migration;
use App\Database\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user', function($table) {
            // Auto includes: id, active, deleted, created_by, updated_by, created_at, updated_at
            
            // Add your custom columns here:
            $table->string('name');
            $table->string('email');
            $table->string('password');
        });
    }

    public function down(): void
    {
        Schema::drop('user');
    }
};