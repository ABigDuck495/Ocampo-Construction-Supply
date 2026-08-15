<?php

namespace Database\Seeders;

use App\Models\Printer;
use Illuminate\Database\Seeder;

class PrinterSeeder extends Seeder
{
    public function run(): void
    {
        Printer::create([
            'name' => 'Front Counter',
            'connection_type' => 'network',
            'ip_address' => '192.168.1.50',
            'port' => 9100,
            'is_active' => true,
            'is_default' => true,
        ]);
    }
}