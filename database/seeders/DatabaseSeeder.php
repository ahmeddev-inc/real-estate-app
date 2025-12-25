<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        // إنشاء المستخدمين
        $this->call(UserSeeder::class);
        
        // إنشاء العقارات
        $this->call(PropertySeeder::class);
        
        // إنشاء العملاء
        $this->call(ClientSeeder::class);
    }
}
