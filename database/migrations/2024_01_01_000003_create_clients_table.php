<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // التصنيف
            $table->string('code', 50)->unique()->comment('مثال: CLIENT-2024-001');
            $table->enum('type', ['buyer', 'seller', 'tenant', 'landlord', 'both'])->default('buyer');
            $table->enum('source', [
                'website',
                'referral',
                'walk_in',
                'phone_call',
                'social_media',
                'advertisement',
                'previous_client',
                'other'
            ])->default('website');
            
            // المعلومات الشخصية
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email')->unique()->nullable();
            $table->string('phone', 20);
            $table->string('phone_2', 20)->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('passport_number', 20)->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('nationality', 50)->nullable();
            
            // معلومات الاتصال
            $table->text('address')->nullable();
            $table->string('city', 100);
            $table->string('district', 100)->nullable();
            $table->string('neighborhood', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            
            // معلومات مهنية
            $table->string('company_name', 200)->nullable();
            $table->string('job_title', 100)->nullable();
            $table->string('industry', 100)->nullable();
            $table->decimal('annual_income', 15, 2)->nullable();
            
            // حالة العميل
            $table->enum('status', [
                'lead',
                'prospect',
                'client',
                'inactive',
                'blacklisted'
            ])->default('lead');
            
            $table->enum('priority', ['low', 'medium', 'high', 'vip'])->default('medium');
            $table->enum('budget_range', [
                'under_1m',
                '1m_2m',
                '2m_5m',
                '5m_10m',
                '10m_20m',
                'over_20m'
            ])->nullable();
            
            // التفضيلات
            $table->json('preferred_property_types')->nullable();
            $table->json('preferred_locations')->nullable();
            $table->json('preferred_amenities')->nullable();
            $table->integer('min_bedrooms')->nullable();
            $table->integer('max_bedrooms')->nullable();
            $table->decimal('min_area', 10, 2)->nullable();
            $table->decimal('max_area', 10, 2)->nullable();
            $table->decimal('min_budget', 15, 2)->nullable();
            $table->decimal('max_budget', 15, 2)->nullable();
            
            // معلومات مالية
            $table->enum('financing_type', ['cash', 'mortgage', 'both'])->default('cash');
            $table->decimal('down_payment', 15, 2)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->boolean('has_mortgage_pre_approval')->default(false);
            $table->string('mortgage_pre_approval_number', 100)->nullable();
            
            // الإسناد
            $table->foreignId('assigned_agent_id')->nullable()->constrained('users');
            $table->foreignId('created_by')->constrained('users');
            
            // تفضيلات التواصل
            $table->boolean('allow_sms')->default(true);
            $table->boolean('allow_email')->default(true);
            $table->boolean('allow_whatsapp')->default(true);
            $table->json('communication_preferences')->nullable();
            
            // التواريخ المهمة
            $table->timestamp('converted_to_client_at')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->timestamp('next_follow_up_at')->nullable();
            
            // ملاحظات
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();
            
            // التوقيتات
            $table->timestamps();
            $table->softDeletes();
            
            // الفهارس
            $table->index(['type', 'status']);
            $table->index(['city', 'status']);
            $table->index(['assigned_agent_id', 'status']);
            $table->index(['priority', 'status']);
            $table->index('created_at');
        });

        DB::statement("COMMENT ON TABLE clients IS 'جدول العملاء والمشترين والبائعين'");
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
