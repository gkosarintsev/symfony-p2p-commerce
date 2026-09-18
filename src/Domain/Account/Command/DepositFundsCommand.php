<?php

declare(strict_types=1);

namespace App\Domain\Account\Command;

final readonly class DepositFundsCommand
{
    public function __construct(
        public string $accountIdentifier, // Account number or user email
        public int $amountCents,
        public ?string $reference = null,
        public ?string $idempotencyKey = null,
    ) {
    }
}
