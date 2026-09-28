<?php

namespace Tests\Unit;

use App\Models\Order;
use Tests\TestCase;

class OrderPaymentStatusTest extends TestCase
{
    public function test_legacy_unpaid_status_is_normalized_to_payable()
    {
        $order = new Order([
            'PaymentStatus' => 'Unpaid',
        ]);

        $this->assertSame('Payable', $order->PaymentStatus);
    }

    public function test_payable_status_is_kept_when_reading_order()
    {
        $order = new Order([
            'PaymentStatus' => 'Payable',
        ]);

        $this->assertSame('Payable', $order->PaymentStatus);
    }
}
