<?php

namespace Tests\Feature;

use App\Http\Controllers\DispatchController;
use App\Models\Dispatch;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Truck;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class DispatchCancellationReleasesOrderBackToPendingTest extends TestCase
{
    use WithoutMiddleware;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id('ProductID');
            $table->string('Product_Name');
            $table->string('SKU');
            $table->string('Unit')->nullable();
            $table->string('Category')->nullable();
            $table->string('SubCategory')->nullable();
            $table->decimal('Price', 12, 2)->nullable();
            $table->string('Pricing_type')->nullable();
            $table->string('Pricing_status')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id('OrderID');
            $table->string('CustomerName');
            $table->string('Address')->nullable();
            $table->string('ContactNumber')->nullable();
            $table->dateTime('OrderDate');
            $table->string('PaymentStatus')->default('Payable');
            $table->string('Status')->default('Pending');
            $table->string('Notes')->nullable();
            $table->unsignedBigInteger('CreatedBy')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id('OrderItemID');
            $table->unsignedBigInteger('OrderID');
            $table->unsignedBigInteger('ProductID');
            $table->decimal('Quantity', 10, 2)->nullable();
            $table->decimal('UnitPrice', 12, 2)->nullable();
            $table->string('Pricing_method')->nullable();
            $table->string('Pricing_status')->nullable();
            $table->string('Status')->default('Pending');
            $table->timestamps();
        });

        Schema::create('trucks', function (Blueprint $table) {
            $table->id('TruckID');
            $table->string('TruckName')->nullable();
            $table->string('TruckNumber')->nullable();
            $table->decimal('Capacity', 10, 2)->nullable();
            $table->string('Status')->default('Available');
            $table->timestamps();
        });

        Schema::create('dispatches', function (Blueprint $table) {
            $table->id('DispatchID');
            $table->unsignedBigInteger('OrderItemID');
            $table->unsignedBigInteger('TruckID');
            $table->dateTime('DispatchDate');
            $table->dateTime('AcceptedAt')->nullable();
            $table->decimal('QuantityDispatched', 10, 2)->nullable();
            $table->string('Status')->default('Pending');
            $table->timestamps();
        });

        Schema::create('dispatch_logs', function (Blueprint $table) {
            $table->id('LogID');
            $table->unsignedBigInteger('DispatchID');
            $table->string('Action');
            $table->text('Notes')->nullable();
            $table->dateTime('LoggedAt');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('dispatch_logs');
        Schema::dropIfExists('dispatches');
        Schema::dropIfExists('trucks');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('products');

        parent::tearDown();
    }

    public function test_cancelled_dispatch_returns_order_and_item_to_pending_status(): void
    {
        $product = Product::create([
            'Product_Name' => 'Cement Bag',
            'SKU' => 'CMT-001',
            'Unit' => 'bag',
            'Category' => 'Materials',
            'SubCategory' => 'Cement',
            'Price' => 150,
            'Pricing_type' => 'Fixed',
            'Pricing_status' => 'Resolved',
        ]);

        $order = Order::create([
            'CustomerName' => 'Test Customer',
            'Address' => 'Test Address',
            'ContactNumber' => '09123456789',
            'OrderDate' => now(),
            'PaymentStatus' => 'Payable',
            'Status' => 'Partially Fulfilled',
            'CreatedBy' => 1,
        ]);

        $orderItem = OrderItem::create([
            'OrderID' => $order->OrderID,
            'ProductID' => $product->ProductID,
            'Quantity' => 10,
            'Status' => 'In Progress',
            'UnitPrice' => 150,
            'Pricing_method' => 'Fixed',
            'Pricing_status' => 'Resolved',
        ]);

        $truck = Truck::create([
            'TruckNumber' => 'TRK-100',
            'Status' => 'Available',
            'Capacity' => 1000,
        ]);

        $dispatch = Dispatch::create([
            'OrderItemID' => $orderItem->OrderItemID,
            'TruckID' => $truck->TruckID,
            'DispatchDate' => now(),
            'QuantityDispatched' => 5,
            'Status' => 'Pending',
        ]);

        $orderItem->update(['Status' => 'In Progress']);
        $dispatch->update(['Status' => 'On Route']);

        $this->assertEquals('In Progress', $orderItem->fresh()->Status);
        $this->assertEquals('Partially Fulfilled', $order->fresh()->Status);

        app(DispatchController::class)->cancel($dispatch);

        $this->assertEquals('Failed', $dispatch->fresh()->Status);
        $this->assertEquals('Pending', $orderItem->fresh()->Status);
        $this->assertEquals('Pending', $order->fresh()->Status);
    }
}
