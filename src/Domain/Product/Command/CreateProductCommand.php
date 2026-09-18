<?php

declare(strict_types=1);

namespace App\Domain\Product\Command;

final readonly class CreateProductCommand
{
    public function __construct(
        public string $sellerEmail,
        public string $title,
        public string $description,
        public int $priceCents,
        public string $category = 'General',
    ) {
    }
}
