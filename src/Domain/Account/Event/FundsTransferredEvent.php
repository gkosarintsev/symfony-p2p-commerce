<?php

declare(strict_types=1);

namespace App\Domain\Account\Event;

use App\Entity\Transaction;

final readonly class FundsTransferredEvent
{
    public function __construct(public Transaction $transaction)
    {
    }
}
