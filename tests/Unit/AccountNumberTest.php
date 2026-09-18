<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Account\Exception\InvalidAmountException;
use App\Domain\Account\ValueObject\AccountNumber;
use PHPUnit\Framework\TestCase;

class AccountNumberTest extends TestCase
{
    public function testGenerateValidAccountNumber(): void
    {
        $accountNumber = AccountNumber::generate();
        $this->assertMatchesRegularExpression('/^ECOMM-\d{4}-\d{4}-\d{4}$/', $accountNumber->getValue());
    }

    public function testInvalidFormatThrowsException(): void
    {
        $this->expectException(InvalidAmountException::class);
        new AccountNumber('INVALID-1234');
    }

    public function testEquals(): void
    {
        $a1 = new AccountNumber('ECOMM-1111-2222-3333');
        $a2 = new AccountNumber('ECOMM-1111-2222-3333');
        $a3 = new AccountNumber('ECOMM-9999-8888-7777');

        $this->assertTrue($a1->equals($a2));
        $this->assertFalse($a1->equals($a3));
    }
}
