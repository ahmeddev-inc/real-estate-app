<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            
            // المعلومات الشخصية
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('email')->unique();
            $table->string('phone', 20)->unique()->nullable();
            $table->string('national_id', 20)->unique()->nullable();
            $table->string('password');
            
            // الصلاحيات والأدوار
            $table->enum('role', [
                'super_admin',
                'admin', 
                'manager',
                'agent',
                'client',
                'owner'
            ])->default('agent');
            
            $table->enum('user_type', [
                'employee',
                'freelancer',
                'owner',
                'client'
            ])->default('employee');
            
            // المعلومات الإضافية
            $table->string('avatar')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 50)->nullable();
            $table->string('country', 50)->default('مصر');
            
            // حالة الحساب
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'pending'
            ])->default('active');
            
            // معلومات الوسيط
            $table->decimal('commission_rate', 5, 2)->default(2.50);
            $table->string('employee_id', 20)->unique()->nullable();
            $table->string('job_title', 100)->nullable();
            
            // العلاقات التنظيمية
            $table->foreignId('manager_id')->nullable()->constrained('users');
            $table->foreignId('branch_id')->nullable();
            
            // الإعدادات
            $table->json('permissions')->nullable();
            $table->json('settings')->nullable();
            
            // التواريخ المهمة
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspension_reason')->nullable();
            
            // التوقيتات
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
            
            // الفهارس
            $table->index(['role', 'status']);
            $table->index(['email', 'status']);
            $table->index(['phone', 'status']);
            $table->index(['manager_id', 'status']);
            $table->index('created_at');
        });

        // إضافة تعليق على الجدول
        DB::statement("COMMENT ON TABLE users IS 'جدول المستخدمين والعملاء والوكلاء'");
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
