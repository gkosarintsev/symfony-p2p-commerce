<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Message;

final readonly class GenerateReceiptPdfMessage
{
    public function __construct(public string $transactionId)
    {
    }
}
