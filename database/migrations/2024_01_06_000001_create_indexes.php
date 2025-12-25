<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // فهارس جدول users
        Schema::table('users', function (Blueprint $table) {
            // فهرس للبريد الإلكتروني (مفهرس بالفعل كمفرد)
            // فهرس للبحث بالاسم
            $table->index(['first_name', 'last_name'], 'users_name_search');
            
            // فهرس للدور والحالة
            $table->index(['role', 'status'], 'users_role_status');
            
            // فهرس للتاريخ
            $table->index('created_at', 'users_created_at');
            $table->index('updated_at', 'users_updated_at');
            
            // فهرس للمواقع الجغرافية (إذا كنت تستخدمها)
            // $table->index(['latitude', 'longitude'], 'users_location');
        });

        // فهارس جدول properties
        Schema::table('properties', function (Blueprint $table) {
            // فهارس البحث الرئيسية
            $table->index('title', 'properties_title');
            $table->index('status', 'properties_status');
            $table->index('type', 'properties_type');
            $table->index('purpose', 'properties_purpose');
            $table->index('price', 'properties_price');
            
            // فهارس مركبة للبحث المتقدم
            $table->index(['city', 'district'], 'properties_location');
            $table->index(['type', 'purpose', 'status'], 'properties_type_purpose_status');
            $table->index(['price', 'status'], 'properties_price_status');
            $table->index(['bedrooms', 'bathrooms'], 'properties_rooms');
            
            // فهارس للتواريخ
            $table->index('created_at', 'properties_created_at');
            $table->index('updated_at', 'properties_updated_at');
            
            // فهرس للمستخدم (المالك/الوسيط)
            $table->index('user_id', 'properties_user_id');
            
            // فهرس للنطاق السعري (للبحث بالمدى)
            $table->index(['min_price', 'max_price'], 'properties_price_range');
        });

        // فهارس جدول clients
        Schema::table('clients', function (Blueprint $table) {
            $table->index(['first_name', 'last_name'], 'clients_name');
            $table->index('email', 'clients_email');
            $table->index('phone', 'clients_phone');
            $table->index('status', 'clients_status');
            $table->index('type', 'clients_type');
            $table->index(['budget_min', 'budget_max'], 'clients_budget');
            $table->index('created_at', 'clients_created_at');
            $table->index('user_id', 'clients_user_id');
        });

        // فهارس جدول tasks
        Schema::table('tasks', function (Blueprint $table) {
            $table->index('title', 'tasks_title');
            $table->index('priority', 'tasks_priority');
            $table->index('status', 'tasks_status');
            $table->index(['due_date', 'priority'], 'tasks_due_priority');
            $table->index('user_id', 'tasks_user_id');
            $table->index('assigned_to', 'tasks_assigned_to');
            $table->index('created_at', 'tasks_created_at');
        });

        // فهارس جدول property_client (المطابقة)
        Schema::table('property_client', function (Blueprint $table) {
            $table->index(['property_id', 'client_id'], 'property_client_match');
            $table->index('match_score', 'property_client_score');
            $table->index('status', 'property_client_status');
            $table->index('created_at', 'property_client_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // حذف جميع الفهارس
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_name_search');
            $table->dropIndex('users_role_status');
            $table->dropIndex('users_created_at');
            $table->dropIndex('users_updated_at');
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex('properties_title');
            $table->dropIndex('properties_status');
            $table->dropIndex('properties_type');
            $table->dropIndex('properties_purpose');
            $table->dropIndex('properties_price');
            $table->dropIndex('properties_location');
            $table->dropIndex('properties_type_purpose_status');
            $table->dropIndex('properties_price_status');
            $table->dropIndex('properties_rooms');
            $table->dropIndex('properties_created_at');
            $table->dropIndex('properties_updated_at');
            $table->dropIndex('properties_user_id');
            $table->dropIndex('properties_price_range');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex('clients_name');
            $table->dropIndex('clients_email');
            $table->dropIndex('clients_phone');
            $table->dropIndex('clients_status');
            $table->dropIndex('clients_type');
            $table->dropIndex('clients_budget');
            $table->dropIndex('clients_created_at');
            $table->dropIndex('clients_user_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex('tasks_title');
            $table->dropIndex('tasks_priority');
            $table->dropIndex('tasks_status');
            $table->dropIndex('tasks_due_priority');
            $table->dropIndex('tasks_user_id');
            $table->dropIndex('tasks_assigned_to');
            $table->dropIndex('tasks_created_at');
        });

        Schema::table('property_client', function (Blueprint $table) {
            $table->dropIndex('property_client_match');
            $table->dropIndex('property_client_score');
            $table->dropIndex('property_client_status');
            $table->dropIndex('property_client_created_at');
        });
    }
};
