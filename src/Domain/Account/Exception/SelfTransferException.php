<?php

declare(strict_types=1);

namespace App\Domain\Account\Exception;

class SelfTransferException extends \DomainException
{
    public function __construct()
    {
        parent::__construct('Cannot transfer money to your own account.');
    }
}
