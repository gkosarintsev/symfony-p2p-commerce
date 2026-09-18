<?php

declare(strict_types=1);

namespace App\Domain\Account\ValueObject;

use App\Domain\Account\Exception\InvalidAmountException;

final readonly class Money implements \Stringable
{
    public const string DEFAULT_CURRENCY = 'USD';

    public function __construct(
        private int $cents,
        private string $currency = self::DEFAULT_CURRENCY,
    ) {
        if ($cents < 0) {
            throw new InvalidAmountException('Money amount cannot be negative.');
        }
    }

    public static function fromCents(int $cents, string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self($cents, $currency);
    }

    public static function fromFloat(float $amount, string $currency = self::DEFAULT_CURRENCY): self
    {
        if ($amount < 0) {
            throw new InvalidAmountException('Money amount cannot be negative.');
        }

        $cents = (int) round($amount * 100);

        return new self($cents, $currency);
    }

    public static function zero(string $currency = self::DEFAULT_CURRENCY): self
    {
        return new self(0, $currency);
    }

    public function getCents(): int
    {
        return $this->cents;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function toFloat(): float
    {
        return $this->cents / 100.0;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->cents + $other->cents, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);
        $result = $this->cents - $other->cents;
        if ($result < 0) {
            throw new InvalidAmountException('Subtraction resulted in a negative balance.');
        }

        return new self($result, $this->currency);
    }

    public function isGreaterThanOrEqual(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents >= $other->cents;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->cents > $other->cents;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function equals(self $other): bool
    {
        return $this->cents === $other->cents && $this->currency === $other->currency;
    }

    public function format(): string
    {
        return sprintf('$%.2f', $this->toFloat());
    }

    public function __toString(): string
    {
        return $this->format();
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidAmountException(sprintf('Cannot operate on different currencies: %s and %s', $this->currency, $other->currency));
        }
    }
}
