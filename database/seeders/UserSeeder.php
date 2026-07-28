<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            'Name' => 'Admin123',
            'Password' => Hash::make('Pass123,'),
            'Role' => 'Admin',
            'Email' => 'admin123@gmail.com',
            'PhoneNumber' => '09064093019'
        ]);
    }
}
