<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique()->comment('CLIENT-2024-001');
            $table->enum('type', ['buyer', 'seller', 'tenant', 'landlord'])->default('buyer');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone');
            $table->string('phone_2')->nullable();
            $table->string('national_id')->nullable();
            $table->enum('status', ['lead', 'prospect', 'client', 'inactive'])->default('lead');
            $table->text('address')->nullable();
            $table->string('city');
            $table->enum('priority', ['low', 'medium', 'high', 'vip'])->default('medium');
            $table->decimal('min_budget', 15, 2)->nullable();
            $table->decimal('max_budget', 15, 2)->nullable();
            $table->json('preferred_property_types')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
