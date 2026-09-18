<?php

declare(strict_types=1);

namespace App\Domain\Account\ValueObject;

use App\Domain\Account\Exception\InvalidAmountException;

final readonly class AccountNumber implements \Stringable
{
    private const string PATTERN = '/^ECOMM-\d{4}-\d{4}-\d{4}$/';

    public function __construct(private string $value)
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new InvalidAmountException(sprintf('Invalid account number format: %s', $value));
        }
    }

    public static function generate(): self
    {
        $part1 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $part2 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);
        $part3 = str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT);

        return new self(sprintf('ECOMM-%s-%s-%s', $part1, $part2, $part3));
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
