<?php

namespace Tests\Feature;

use App\Models\Dispatch;
use App\Models\Driver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DriverAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('drivers', function (Blueprint $table) {
            $table->id('DriverID');
            $table->string('Name');
            $table->string('PhoneNumber')->nullable();
            $table->timestamps();
        });

        Schema::create('dispatches', function (Blueprint $table) {
            $table->id('DispatchID');
            $table->unsignedBigInteger('OrderItemID')->nullable();
            $table->unsignedBigInteger('TruckID')->nullable();
            $table->timestamp('DispatchDate')->nullable();
            $table->decimal('QuantityDispatched', 10, 2)->default(1);
            $table->enum('Status', ['Pending', 'On Route', 'Delivered', 'Failed'])->default('Pending');
            $table->timestamps();
        });

        Schema::create('dispatch_drivers', function (Blueprint $table) {
            $table->id('DispatchDriverID');
            $table->unsignedBigInteger('DispatchID');
            $table->unsignedBigInteger('DriverID');
            $table->enum('Role', ['Driver', 'Helper'])->default('Driver');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dispatch_drivers');
        Schema::dropIfExists('dispatches');
        Schema::dropIfExists('drivers');

        parent::tearDown();
    }

    public function test_only_non_active_dispatch_drivers_are_listed_as_available(): void
    {
        $activePending = Driver::create(['Name' => 'Pending Driver']);
        $activeOnRoute = Driver::create(['Name' => 'Route Driver']);
        $availableDelivered = Driver::create(['Name' => 'Delivered Driver']);
        $availableFailed = Driver::create(['Name' => 'Failed Driver']);
        $fullyAvailable = Driver::create(['Name' => 'Free Driver']);

        $pendingDispatch = Dispatch::create(['OrderItemID' => 1, 'TruckID' => 1, 'DispatchDate' => now(), 'QuantityDispatched' => 5, 'Status' => 'Pending']);
        $onRouteDispatch = Dispatch::create(['OrderItemID' => 2, 'TruckID' => 2, 'DispatchDate' => now(), 'QuantityDispatched' => 5, 'Status' => 'On Route']);
        $deliveredDispatch = Dispatch::create(['OrderItemID' => 3, 'TruckID' => 3, 'DispatchDate' => now(), 'QuantityDispatched' => 5, 'Status' => 'Delivered']);
        $failedDispatch = Dispatch::create(['OrderItemID' => 4, 'TruckID' => 4, 'DispatchDate' => now(), 'QuantityDispatched' => 5, 'Status' => 'Failed']);

        $pendingDispatch->drivers()->attach($activePending->DriverID, ['Role' => 'Driver']);
        $onRouteDispatch->drivers()->attach($activeOnRoute->DriverID, ['Role' => 'Driver']);
        $deliveredDispatch->drivers()->attach($availableDelivered->DriverID, ['Role' => 'Driver']);
        $failedDispatch->drivers()->attach($availableFailed->DriverID, ['Role' => 'Driver']);

        $availableIds = Driver::available()->pluck('DriverID')->all();

        $this->assertNotContains($activePending->DriverID, $availableIds);
        $this->assertNotContains($activeOnRoute->DriverID, $availableIds);
        $this->assertContains($availableDelivered->DriverID, $availableIds);
        $this->assertContains($availableFailed->DriverID, $availableIds);
        $this->assertContains($fullyAvailable->DriverID, $availableIds);
    }
}
