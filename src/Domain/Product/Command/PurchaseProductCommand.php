<?php

declare(strict_types=1);

namespace App\Domain\Product\Command;

final readonly class PurchaseProductCommand
{
    public function __construct(
        public string $buyerEmail,
        public string $productId,
        public ?string $idempotencyKey = null,
    ) {
    }
}
