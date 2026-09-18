<?php

declare(strict_types=1);

namespace App\Domain\Account\Exception;

class AccountNotFoundException extends \DomainException
{
    public function __construct(string $identifier)
    {
        parent::__construct(sprintf('Account "%s" was not found.', $identifier));
    }
}
