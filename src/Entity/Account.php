<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Account\Exception\InsufficientFundsException;
use App\Domain\Account\ValueObject\AccountNumber;
use App\Domain\Account\ValueObject\Money;
use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
#[ORM\Index(name: 'idx_accounts_account_number', columns: ['account_number'])]
class Account
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\OneToOne(inversedBy: 'account', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 32, unique: true)]
    private string $accountNumber;

    #[ORM\Column(type: Types::BIGINT)]
    private int $balance = 0;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency = 'USD';

    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, string $accountNumber, string $currency = 'USD')
    {
        $this->id = Uuid::v7();
        $this->user = $user;
        $this->accountNumber = $accountNumber;
        $this->currency = $currency;
        $this->balance = 0;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getAccountNumber(): string
    {
        return $this->accountNumber;
    }

    public function getAccountNumberVo(): AccountNumber
    {
        return new AccountNumber($this->accountNumber);
    }

    public function getBalance(): int
    {
        return $this->balance;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMoney(): Money
    {
        return Money::fromCents($this->balance, $this->currency);
    }

    public function credit(Money $amount): void
    {
        $newMoney = $this->getMoney()->add($amount);
        $this->balance = $newMoney->getCents();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function debit(Money $amount): void
    {
        if (!$this->getMoney()->isGreaterThanOrEqual($amount)) {
            throw new InsufficientFundsException($amount->getCents(), $this->balance);
        }

        $newMoney = $this->getMoney()->subtract($amount);
        $this->balance = $newMoney->getCents();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
