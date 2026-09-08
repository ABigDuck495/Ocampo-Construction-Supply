<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SystemSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $now = Carbon::now();

        $settings = [
                //Inventory settings
            [
                'Setting_Key'   => 'enable_Inventory_tracking',
                'Setting_Value' => 'true',
                'Setting_Group' => 'Inventory',
                'Setting_Description'   => 'Toggles automatic stock deduction on sales. Set to "false" to bypass Inventory limits.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'allow_negative_Inventory',
                'Setting_Value' => 'false',
                'Setting_Group' => 'Inventory',
                'Setting_Description'   => 'Allows Inventory counts to fall below zero when making sales without available stock.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'allow_unresolved_price_checkout',
                'Setting_Value' => 'true',
                'Setting_Group' => 'Inventory',
                'Setting_Description'   => 'Determines if an order with unresolved variable prices can complete checkout or dispatch.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'default_outsourced_markup_percent',
                'Setting_Value' => '15.00',
                'Setting_Group' => 'Inventory',
                'Setting_Description'   => 'Default percentage markup auto-applied to variable cost items purchased from external stores.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
                //TRUCKS shit
            [
                'Setting_Key'   => 'enable_truck_capacity_tracking',
                'Setting_Value' => 'true',
                'Setting_Group' => 'Logistics',
                'Setting_Description'   => 'Toggles weight/volume limit verification when scheduling delivery dispatches.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'truck_capacity_unit',
                'Setting_Value' => 'kg',
                'Setting_Group' => 'Logistics',
                'Setting_Description'   => 'Default measurement unit for fleet limits and delivery load calculation (e.g., kg, lbs, m3).',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'max_default_truck_capacity',
                'Setting_Value' => '1000.00',
                'Setting_Group' => 'Logistics',
                'Setting_Description'   => 'Fallback maximum payload capacity if a specific vehicle limit is not configured.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'block_oversized_orders',
                'Setting_Value' => 'false',
                'Setting_Group' => 'Logistics',
                'Setting_Description'   => 'Blocks dispatch if payload exceeds truck capacity ("true") or displays warning ("false").',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
                //POS shits
            [
                'Setting_Key'   => 'allow_price_override',
                'Setting_Value' => 'false',
                'Setting_Group' => 'POS',
                'Setting_Description'   => 'Allows cashiers to manually change standard fixed item prices at checkout without approval.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'enable_unit_conversion',
                'Setting_Value' => 'true',
                'Setting_Group' => 'POS',
                'Setting_Description'   => 'Enables selling in sub-units (e.g., grams) while stock is tracked in base units (e.g., kilograms).',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'default_tax_rate',
                'Setting_Value' => '12.00',
                'Setting_Group' => 'POS',
                'Setting_Description'   => 'Standard sales tax percentage applied to taxable order totals.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'Setting_Key'   => 'tax_inclusive_pricing',
                'Setting_Value' => 'false',
                'Setting_Group' => 'POS',
                'Setting_Description'   => 'Indicates if product standard list prices already include sales tax.',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('System_Settings')->updateOrInsert(
                ['Setting_Key' => $setting['Setting_Key']],
                $setting
            );
        }
    }
}
