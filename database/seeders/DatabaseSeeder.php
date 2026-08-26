<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Faker\Factory as Faker;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Disable foreign key checks to avoid constraint errors
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Truncate all tables (order matters due to foreign keys, but we'll truncate all)
        $tables = [
            'deliveries', 'dispatch_drivers', 'dispatches', 'order_items', 'orders',
            'transactions', 'inventory', 'products', 'drivers', 'trucks', 'users',
            'reports', 'sessions', 'jobs', 'job_batches', 'failed_jobs'
        ];
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // ----------------------------------------
        // 1. Users
        // ----------------------------------------
        $users = [
            [
                'Name'        => 'Admin123',
                'Email'       => 'admin123@gmail.com',
                'Password'    => Hash::make('Pass123'),
                'Role'        => 'Admin',
                'PhoneNumber' => '09064093019',
            ],
            [
                'Name'        => 'Staff',
                'Email'       => 'staff123@gmail.com',
                'Password'    => Hash::make('Pass123'),
                'Role'        => 'Staff',
                'PhoneNumber' => '09774484907',
            ],
        ];

        $createdUsers = [];
        foreach ($users as $user) {
            $id = DB::table('users')->insertGetId($user);
            $createdUsers[] = $id;
        }

        $adminId = DB::table('users')->where('Email', 'admin123@gmail.com')->value('UserID');
        $staffId = DB::table('users')->where('Email', 'staff123@gmail.com')->value('UserID');

        // ----------------------------------------
        // 2. Products – from the full Excel list
        // ----------------------------------------
        $productRows = $this->getProductRows();
        $productIds = [];

        foreach ($productRows as $row) {
            // Extract fields
            $productName = trim($row['Name']);
            $category     = trim($row['Category']);   // Used as SubCategory below
            $priceRaw     = trim($row['Price']);

            // Determine Unit (try to infer from product name)
            $unit = $this->inferUnit($productName);

            // Price: convert to float, treat 'variable' or non‑numeric as 0
            $price = 0.0;
            if (is_numeric($priceRaw)) {
                $price = (float) $priceRaw;
            }

            // For SubCategory we use the Excel Category, for Category we use a generic 'General'
            // but we could also set Category = SubCategory if preferred.
            $subCategory = $category;
            $categoryGeneral = 'General'; // You can change this logic if needed

            // SKU: generate a unique slug from the product name (or leave null)
            $sku = \Illuminate\Support\Str::slug($productName, '-') . '-' . uniqid();

            $id = DB::table('products')->insertGetId([
                'Product_Name' => $productName,
                'Category'     => $categoryGeneral,
                'SubCategory'  => $subCategory,
                'Unit'         => $unit,
                'SKU'          => $sku, // nullable, but we generate one
                'Price'        => $price,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $productIds[] = $id;
        }

        // ----------------------------------------
        // 3. Inventory
        // ----------------------------------------
        foreach ($productIds as $pid) {
            DB::table('inventory')->insert([
                'ProductID'      => $pid,
                'QuantityOnHand' => $faker->numberBetween(50, 500),
                'ReorderLevel'   => $faker->numberBetween(10, 30),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        // ----------------------------------------
        // 4. Trucks
        // ----------------------------------------
        $trucks = [
            ['TruckName' => 'Isuzu Elf',    'PlateNumber' => 'ABC-1234', 'Capacity' => 4.5, 'Status' => 'Available'],
            ['TruckName' => 'Mitsubishi Fuso', 'PlateNumber' => 'XYZ-5678', 'Capacity' => 8.0, 'Status' => 'Available'],
            ['TruckName' => 'Ford Transit', 'PlateNumber' => 'DEF-9012', 'Capacity' => 3.0, 'Status' => 'Unavailable'],
        ];
        $truckIds = [];
        foreach ($trucks as $t) {
            $t['created_at'] = now();
            $t['updated_at'] = now();
            $truckIds[] = DB::table('trucks')->insertGetId($t);
        }

        // ----------------------------------------
        // 5. Drivers
        // ----------------------------------------
        $drivers = [
            ['Name' => 'Juan Dela Cruz', 'PhoneNumber' => '09111234567'],
            ['Name' => 'Maria Santos',   'PhoneNumber' => '09122345678'],
            ['Name' => 'Pedro Reyes',    'PhoneNumber' => '09133456789'],
            ['Name' => 'Jose Rizal',     'PhoneNumber' => '09144567890'],
        ];
        $driverIds = [];
        foreach ($drivers as $d) {
            $d['created_at'] = now();
            $d['updated_at'] = now();
            $driverIds[] = DB::table('drivers')->insertGetId($d);
        }

        // ----------------------------------------
        // 6. Orders
        // ----------------------------------------
        $orderStatuses = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
        $paymentStatuses = ['Paid', 'Unpaid'];
        $orderIds = [];

        for ($i = 0; $i < 20; $i++) {
            $orderId = DB::table('orders')->insertGetId([
                'CustomerName'  => $faker->name,
                'Address'       => $faker->address,
                'ContactNumber' => $faker->phoneNumber,
                'OrderDate'     => $faker->dateTimeBetween('-1 month', 'now'),
                'PaymentStatus' => $faker->randomElement($paymentStatuses),
                'Status'        => $faker->randomElement($orderStatuses),
                'Notes'         => $faker->optional()->sentence,
                'CreatedBy'     => $faker->randomElement([$adminId, $staffId]),
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
            $orderIds[] = $orderId;
        }

        // ----------------------------------------
        // 7. Order Items & Dispatches, Deliveries, DispatchDrivers
        // ----------------------------------------
        foreach ($orderIds as $orderId) {
            $numItems = $faker->numberBetween(1, 4);
            for ($j = 0; $j < $numItems; $j++) {
                $productId = $faker->randomElement($productIds);
                $qty = $faker->numberBetween(1, 20);

                $orderItemId = DB::table('order_items')->insertGetId([
                    'OrderID'    => $orderId,
                    'ProductID'  => $productId,
                    'Quantity'   => $qty,
                    'Status'     => $faker->randomElement(['Pending', 'In Progress', 'Completed']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($faker->boolean(70)) {
                    $dispatchStatus = $faker->randomElement(['Pending', 'On Route', 'Delivered']);
                    $dispatchId = DB::table('dispatches')->insertGetId([
                        'OrderItemID'       => $orderItemId,
                        'TruckID'           => $faker->optional(0.6)->randomElement($truckIds),
                        'DispatchDate'      => $faker->dateTimeBetween('-2 weeks', 'now'),
                        'QuantityDispatched'=> $faker->numberBetween(1, $qty),
                        'Status'            => $dispatchStatus,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ]);

                    if ($faker->boolean(80)) {
                        DB::table('deliveries')->insert([
                            'DispatchID'      => $dispatchId,
                            'DeliveryDate'    => $faker->dateTimeBetween('-1 week', 'now'),
                            'QuantityDelivered'=> $faker->numberBetween(1, $qty),
                            'Status'          => $faker->randomElement(['Delivered', 'Failed']),
                            'Notes'           => $faker->optional()->sentence,
                            'created_at'      => now(),
                            'updated_at'      => now(),
                        ]);
                    }

                    $numDrivers = $faker->numberBetween(1, 2);
                    $assignedDrivers = $faker->randomElements($driverIds, $numDrivers);
                    foreach ($assignedDrivers as $driverId) {
                        DB::table('dispatch_drivers')->insert([
                            'DispatchID' => $dispatchId,
                            'DriverID'   => $driverId,
                            'Role'       => ($numDrivers > 1 && $driverId === $assignedDrivers[0]) ? 'Driver' : 'Helper',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        // ----------------------------------------
        // 8. Transactions (for paid orders)
        // ----------------------------------------
        $paidOrderIds = DB::table('orders')->where('PaymentStatus', 'Paid')->pluck('OrderID');
        foreach ($paidOrderIds as $orderId) {
            DB::table('transactions')->insert([
                'OrderID'         => $orderId,
                'TransactionDate' => $faker->dateTimeBetween('-1 month', 'now'),
                'Amount'          => $faker->randomFloat(2, 100, 5000),
                'PaymentMethod'   => $faker->randomElement(['COD', 'GCash', 'Card', 'Bank Transfer']),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // ----------------------------------------
        // 9. Reports (daily summary)
        // ----------------------------------------
        for ($day = 0; $day < 30; $day++) {
            $date = now()->subDays($day)->toDateString();
            DB::table('reports')->insert([
                'ReportDate'     => $date,
                'GeneratedAt'    => now(),
                'TotalOrders'    => $faker->numberBetween(5, 30),
                'TotalSales'     => $faker->numberBetween(1000, 50000),
                'TotalItemsSold' => $faker->numberBetween(20, 200),
                'TotalDeliveries'=> $faker->numberBetween(5, 25),
                'TotalDispatches'=> $faker->numberBetween(10, 40),
                'Notes'          => $faker->optional()->sentence,
            ]);
        }

        // ----------------------------------------
        // 10. Session (optional)
        // ----------------------------------------
        DB::table('sessions')->insert([
            'id'            => 'test-session-id',
            'user_id'       => $adminId,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'Mozilla/5.0 (Seeder)',
            'payload'       => 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoi...',
            'last_activity' => now()->timestamp,
        ]);

        $this->command->info('Database seeded successfully!');
    }

    /**
     * Parse the Excel product list from the raw table string.
     * The table must have columns: Name, Category, Cost, Price.
     * Returns an array of associative arrays.
     */
    private function getProductRows(): array
    {
        // Copy the entire table from the OCS inventory clean.xlsx file.
        // Include the header row and the separator line; they will be skipped.
        $rawTable = <<<TABLE
| Name | Category | Cost | Price |
|:-----|:---------|:-----|:------|
| 0.4 x 10 FT Long Span | 0.4 Long Span | 0.0 | 850.0 |
| 0.4 x 11 FT Long Span | 0.4 Long Span | 0.0 | 935.0 |
| 0.4 x 12 FT Long Span | 0.4 Long Span | 0.0 | 1020.0 |
| 0.4 x 13 FT Long Span | 0.4 Long Span | 0.0 | 1105.0 |
| 0.4 x 14 FT Long Span | 0.4 Long Span | 0.0 | 1190.0 |
| ... (paste all remaining rows here) ...
| W. Square WOOD HANDLE Shovel |  |  |  |
TABLE;

        $lines = explode("\n", $rawTable);
        $rows = [];

        // Skip header (index 0) and separator (index 1)
        for ($i = 2; $i < count($lines); $i++) {
            $line = trim($lines[$i]);
            if (empty($line)) continue;

            // Split by pipe, but only if the line starts with '|'
            if (strpos($line, '|') === 0) {
                $parts = array_map('trim', explode('|', $line));
                // parts: [0] empty, [1] Name, [2] Category, [3] Cost, [4] Price, [5] empty
                if (count($parts) >= 5) {
                    $rows[] = [
                        'Name'     => $parts[1] ?? '',
                        'Category' => $parts[2] ?? '',
                        'Cost'     => $parts[3] ?? '0.0',
                        'Price'    => $parts[4] ?? '0.0',
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * Infer the unit of measure from the product name.
     * You can expand this logic as needed.
     */
    private function inferUnit(string $name): string
    {
        $name = strtolower($name);

        if (strpos($name, 'gal') !== false || strpos($name, 'gallon') !== false) {
            return 'gallon';
        }
        if (strpos($name, 'ltr') !== false || strpos($name, 'liter') !== false) {
            return 'liter';
        }
        if (strpos($name, 'kg') !== false) {
            return 'kg';
        }
        if (strpos($name, 'box') !== false) {
            return 'box';
        }
        if (strpos($name, 'pcs') !== false || strpos($name, 'piece') !== false) {
            return 'pcs';
        }
        // Default
        return 'pcs';
    }
}