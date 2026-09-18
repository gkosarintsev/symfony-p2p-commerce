<?php

declare(strict_types=1);

namespace App\Domain\Account\Command;

final readonly class TransferFundsCommand
{
    public function __construct(
        public string $sourceIdentifier,      // Source account number or email
        public string $destinationIdentifier, // Recipient account number or email
        public int $amountCents,
        public ?string $reference = null,
        public ?string $idempotencyKey = null,
    ) {
    }
}
