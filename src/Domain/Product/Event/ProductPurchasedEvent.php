<?php

declare(strict_types=1);

namespace App\Domain\Product\Event;

use App\Entity\Order;

final readonly class ProductPurchasedEvent
{
    public function __construct(public Order $order)
    {
    }
}
