<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Account\Exception\InvalidAmountException;
use App\Domain\Account\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function testFromCentsAndFloat(): void
    {
        $m1 = Money::fromCents(1050);
        $this->assertSame(1050, $m1->getCents());
        $this->assertSame(10.50, $m1->toFloat());
        $this->assertSame('$10.50', $m1->format());

        $m2 = Money::fromFloat(25.99);
        $this->assertSame(2599, $m2->getCents());
        $this->assertSame(25.99, $m2->toFloat());
        $this->assertSame('$25.99', $m2->format());
    }

    public function testNegativeAmountThrowsException(): void
    {
        $this->expectException(InvalidAmountException::class);
        Money::fromCents(-100);
    }

    public function testAddAndSubtract(): void
    {
        $m1 = Money::fromCents(5000);
        $m2 = Money::fromCents(2000);

        $sum = $m1->add($m2);
        $this->assertSame(7000, $sum->getCents());

        $diff = $m1->subtract($m2);
        $this->assertSame(3000, $diff->getCents());
    }

    public function testSubtractResultingInNegativeThrowsException(): void
    {
        $m1 = Money::fromCents(1000);
        $m2 = Money::fromCents(2000);

        $this->expectException(InvalidAmountException::class);
        $m1->subtract($m2);
    }

    public function testDifferentCurrenciesThrowException(): void
    {
        $usd = Money::fromCents(1000, 'USD');
        $eur = Money::fromCents(1000, 'EUR');

        $this->expectException(InvalidAmountException::class);
        $usd->add($eur);
    }

    public function testComparisons(): void
    {
        $m1 = Money::fromCents(5000);
        $m2 = Money::fromCents(5000);
        $m3 = Money::fromCents(2000);

        $this->assertTrue($m1->isGreaterThanOrEqual($m2));
        $this->assertTrue($m1->isGreaterThanOrEqual($m3));
        $this->assertFalse($m3->isGreaterThanOrEqual($m1));
        $this->assertTrue($m1->equals($m2));
        $this->assertFalse($m1->equals($m3));
    }
}
