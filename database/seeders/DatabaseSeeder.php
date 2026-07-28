<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'Name' => 'Admin123',
            'Password' => Hash::make('Pass123'),
            'Role' => 'Admin',
            'Email' => 'admin123@gmail.com',
            'PhoneNumber' => '09064093019'
        ]);
        
        User::create([
            'Name' => 'Staff123',
            'Password' => Hash::make('Pass123'),
            'Role' => 'Staff',
            'Email' => 'staff123@gmail.com',
            'PhoneNumber' => '09774484907'
        ]);
    }
}
