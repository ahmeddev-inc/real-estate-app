<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // المعلومات الأساسية
            $table->string('code', 50)->unique()->comment('مثال: PROP-2024-001');
            $table->string('title', 200);
            $table->text('description')->nullable();
            
            // التصنيف
            $table->enum('type', [
                'apartment',
                'villa', 
                'townhouse',
                'duplex',
                'penthouse',
                'studio',
                'commercial',
                'land',
                'building',
                'chalet'
            ])->default('apartment');
            
            $table->enum('purpose', ['sale', 'rent', 'both'])->default('sale');
            $table->enum('status', [
                'draft',
                'available',
                'reserved',
                'sold',
                'rented',
                'under_contract',
                'inactive'
            ])->default('draft');
            
            // الموقع
            $table->text('address');
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('google_maps_url', 500)->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            
            // المواصفات
            $table->integer('bedrooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('living_rooms')->nullable();
            $table->integer('kitchens')->nullable();
            $table->decimal('built_area', 10, 2)->nullable()->comment('المساحة المبانية');
            $table->decimal('land_area', 10, 2)->nullable()->comment('مساحة الأرض');
            $table->integer('floor')->nullable();
            $table->integer('total_floors')->nullable();
            $table->integer('year_built')->nullable();
            $table->enum('furnishing', ['furnished', 'semi_furnished', 'unfurnished'])->nullable();
            
            // التسعير
            $table->decimal('price_egp', 15, 2);
            $table->decimal('price_usd', 15, 2)->nullable();
            $table->enum('price_type', ['fixed', 'negotiable', 'by_meter'])->default('fixed');
            $table->decimal('price_per_meter', 15, 2)->nullable();
            $table->decimal('commission_amount', 15, 2)->nullable();
            $table->decimal('commission_rate', 5, 2)->default(2.5);
            
            // الملكية والإدارة
            $table->foreignId('owner_id')->nullable()->constrained('users');
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            
            // الوسائط
            $table->json('images')->nullable();
            $table->json('documents')->nullable();
            $table->json('videos')->nullable();
            
            // المميزات والخدمات
            $table->json('features')->nullable();
            $table->json('amenities')->nullable();
            
            // المعلومات المالية والقانونية
            $table->boolean('has_mortgage')->default(false);
            $table->text('mortgage_details')->nullable();
            $table->boolean('has_maintenance')->default(false);
            $table->decimal('maintenance_fee', 10, 2)->nullable();
            $table->string('property_tax_number', 50)->nullable();
            $table->string('deed_number', 50)->nullable();
            
            // الحالة والرؤية
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->integer('view_count')->default(0);
            $table->integer('inquiry_count')->default(0);
            
            // التواريخ
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_to')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('rented_at')->nullable();
            $table->timestamp('reserved_at')->nullable();
            
            // التوقيتات
            $table->timestamps();
            $table->softDeletes();
            
            // الفهارس
            $table->index(['type', 'status']);
            $table->index(['city', 'status']);
            $table->index(['price_egp', 'status']);
            $table->index(['owner_id', 'assigned_agent_id']);
            $table->index(['is_featured', 'status']);
            $table->index(['created_at', 'status']);
        });

        DB::statement("COMMENT ON TABLE properties IS 'جدول العقارات والعقارات'");
    }

    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
