<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('System_Settings', function (Blueprint $table) {
            $table->id('Setting_ID');
            $table->string('Setting_Key');
            $table->text('Setting_Value')->nullable();
            $table->string('Setting_Group')->nullable();
            $table->text('Setting_Description')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('System_Settings');

        parent::tearDown();
    }

    public function test_legacy_setting_keys_are_resolved_by_canonical_name(): void
    {
        SystemSetting::query()->create([
            'Setting_Key' => 'enable_Inventory_tracking',
            'Setting_Value' => 'true',
            'Setting_Group' => 'Inventory',
            'Setting_Description' => 'Legacy key compatibility test',
        ]);

        $this->assertTrue(SystemSetting::get('enable_inventory_tracking'));
        $this->assertArrayHasKey('enable_inventory_tracking', SystemSetting::allCached());
        $this->assertTrue(SystemSetting::allCached()['enable_inventory_tracking']);
    }
}
