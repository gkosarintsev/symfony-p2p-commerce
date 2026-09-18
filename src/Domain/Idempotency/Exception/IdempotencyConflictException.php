<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Exception;

class IdempotencyConflictException extends \DomainException
{
    public function __construct(string $key)
    {
        parent::__construct(sprintf('Transaction with idempotency key "%s" is currently in progress.', $key));
    }
}
