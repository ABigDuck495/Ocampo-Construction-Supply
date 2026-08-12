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
        // 2. Products
        // ----------------------------------------
        $products = [
            'Portland Cement (40kg)'      => ['Cement',     'OPC Type 1'],
            'Fine Sand (1 cu.m.)'         => ['Aggregates', 'Sand'],
            'Gravel (3/4")'               => ['Aggregates', 'Gravel'],
            'Steel Rebar (10mm)'          => ['Steel',      'Rebar'],
            'PVC Pipe (4")'               => ['Plumbing',   'Pipe'],
            'Paint - White (4L)'          => ['Paint',      'Latex'],
            'Roofing Sheet (Galvanized)'  => ['Roofing',    'Galvanized Sheet'],
            'Wood Lumber (2x4x10)'        => ['Lumber',     'Framing Wood'],
            'Nails (4")'                  => ['Hardware',   'Nails'],
            'Concrete Hollow Blocks (6")' => ['Masonry',    'CHB'],
        ];
        $productIds = [];
        foreach ($products as $name => $cat) {
            [$category, $subCategory] = $cat;
            $id = DB::table('products')->insertGetId([
                'Product_Name' => $name,
                'Category'     => $category,
                'SubCategory'  => $subCategory,
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
            // Each order has 1 to 4 items
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

                // 70% chance that this order item has a dispatch
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

                    // 80% chance that dispatch has a delivery
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

                    // Assign 1 or 2 drivers to this dispatch
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
        // 8. Transactions (for orders that are paid)
        // ----------------------------------------
        $paidOrderIds = DB::table('orders')->where('PaymentStatus', 'Paid')->pluck('OrderID');
        foreach ($paidOrderIds as $orderId) {
            DB::table('transactions')->insert([
                'OrderID'         => $orderId,
                'TransactionDate' => $faker->dateTimeBetween('-1 month', 'now'),
                'Amount'          => $faker->randomFloat(2, 100, 5000),
                'PaymentMethod'   => $faker->randomElement(['Cash', 'Credit', 'Cash On Delivery']),
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);
        }

        // ----------------------------------------
        // 9. Reports (daily summary for last 30 days)
        // ----------------------------------------
        for ($day = 0; $day < 30; $day++) {
            $date = now()->subDays($day)->toDateString();
            $totalOrders = $faker->numberBetween(5, 30);
            $totalSales = $faker->numberBetween(1000, 50000);
            $totalItemsSold = $faker->numberBetween(20, 200);
            $totalDeliveries = $faker->numberBetween(5, 25);
            $totalDispatches = $faker->numberBetween(10, 40);

            DB::table('reports')->insert([
                'ReportDate'     => $date,
                'GeneratedAt'    => now(),
                'TotalOrders'    => $totalOrders,
                'TotalSales'     => $totalSales,
                'TotalItemsSold' => $totalItemsSold,
                'TotalDeliveries'=> $totalDeliveries,
                'TotalDispatches'=> $totalDispatches,
                'Notes'          => $faker->optional()->sentence,
            ]);
        }

        // ----------------------------------------
        // 10. Session (optional – just one for testing)
        // ----------------------------------------
        DB::table('sessions')->insert([
            'id'            => 'test-session-id',
            'user_id'       => $adminId,
            'ip_address'    => '127.0.0.1',
            'user_agent'    => 'Mozilla/5.0 (Seeder)',
            'payload'       => 'YTo0OntzOjY6Il90b2tlbiI7czo0MDoi...', // dummy payload
            'last_activity' => now()->timestamp,
        ]);

        $this->command->info('Database seeded successfully!');
    }
}