<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique();
            $table->string('phone')->unique()->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'manager', 'agent', 'client', 'owner'])->default('agent');
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('avatar')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('national_id')->unique()->nullable();
            $table->decimal('commission_rate', 5, 2)->default(2.50);
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
