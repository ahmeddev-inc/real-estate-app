<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code')->unique()->comment('PROP-2024-001');
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['apartment', 'villa', 'townhouse', 'duplex', 'land', 'commercial', 'chalet'])->default('apartment');
            $table->enum('purpose', ['sale', 'rent'])->default('sale');
            $table->enum('status', ['draft', 'available', 'reserved', 'sold', 'rented', 'inactive'])->default('draft');
            $table->string('address');
            $table->string('city');
            $table->string('district')->nullable();
            $table->decimal('price_egp', 15, 2);
            $table->decimal('price_usd', 15, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(2.50);
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->decimal('area', 10, 2)->nullable();
            $table->integer('year_built')->nullable();
            $table->json('features')->nullable();
            $table->json('images')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->integer('view_count')->default(0);
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('rented_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
