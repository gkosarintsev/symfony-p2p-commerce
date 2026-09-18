<?php

declare(strict_types=1);

namespace App\Domain\Notification\Message;

final readonly class SendPurchaseNotificationMessage
{
    public function __construct(
        public string $orderId,
        public string $buyerEmail,
        public string $sellerEmail,
        public string $productTitle,
        public int $amountCents,
    ) {
    }
}
