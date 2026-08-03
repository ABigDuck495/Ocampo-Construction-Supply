<?php

namespace Tests\Unit;

use App\Models\OrderItem;
use PHPUnit\Framework\TestCase;

class OrderItemStatusTest extends TestCase
{
    public function test_allowed_statuses_match_the_migration_values(): void
    {
        $this->assertSame(['Pending', 'In Progress', 'Completed'], OrderItem::allowedStatuses());
    }

    public function test_legacy_fulfillment_statuses_are_normalized(): void
    {
        $this->assertSame('In Progress', OrderItem::normalizeStatus('Partially Fulfilled'));
        $this->assertSame('Completed', OrderItem::normalizeStatus('Fulfilled'));
    }
}
