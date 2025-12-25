<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        // إنشاء المدير العام
        User::create([
            'uuid' => \Illuminate\Support\Str::uuid(),
            'first_name' => 'المدير',
            'last_name' => 'العام',
            'email' => 'admin@aakerz.com',
            'phone' => '01000000001',
            'password' => Hash::make('Admin@123'),
            'role' => 'super_admin',
            'user_type' => 'employee',
            'city' => 'القاهرة',
            'status' => 'active',
            'commission_rate' => 0,
        ]);

        // إنشاء وكلاء
        User::factory()->count(5)->agent()->create();

        // إنشاء عملاء
        User::factory()->count(10)->client()->create();

        // إنشاء ملاك
        User::factory()->count(3)->create([
            'role' => 'owner',
            'user_type' => 'owner',
        ]);
    }
}
