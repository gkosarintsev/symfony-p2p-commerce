<?php

declare(strict_types=1);

namespace App\Enum;

enum TransactionType: string
{
    case DEPOSIT = 'DEPOSIT';
    case TRANSFER = 'TRANSFER';
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case WITHDRAWAL = 'WITHDRAWAL';
}
