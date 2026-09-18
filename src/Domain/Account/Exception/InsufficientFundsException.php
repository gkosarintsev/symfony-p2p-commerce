<?php

declare(strict_types=1);

namespace App\Domain\Account\Exception;

class InsufficientFundsException extends \DomainException
{
    public function __construct(int $requestedCents, int $availableCents)
    {
        parent::__construct(sprintf(
            'Insufficient funds. Requested: $%.2f, Available: $%.2f',
            $requestedCents / 100.0,
            $availableCents / 100.0
        ));
    }
}
