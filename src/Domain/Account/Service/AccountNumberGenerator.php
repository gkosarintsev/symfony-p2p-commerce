<?php

declare(strict_types=1);

namespace App\Domain\Account\Service;

use App\Domain\Account\ValueObject\AccountNumber;
use App\Repository\AccountRepository;

class AccountNumberGenerator
{
    public function __construct(private readonly AccountRepository $accountRepository)
    {
    }

    public function generateUnique(): AccountNumber
    {
        do {
            $accountNumber = AccountNumber::generate();
            $exists = $this->accountRepository->findByAccountNumber($accountNumber->getValue());
        } while (null !== $exists);

        return $accountNumber;
    }
}
