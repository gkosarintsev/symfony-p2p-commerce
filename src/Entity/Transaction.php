<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Account\ValueObject\Money;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
#[ORM\Index(name: 'idx_transactions_idempotency_key', columns: ['idempotency_key'])]
#[ORM\Index(name: 'idx_transactions_source_created', columns: ['source_account_id', 'created_at'])]
#[ORM\Index(name: 'idx_transactions_dest_created', columns: ['destination_account_id', 'created_at'])]
class Transaction
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'source_account_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Account $sourceAccount;

    #[ORM\ManyToOne(targetEntity: Account::class)]
    #[ORM\JoinColumn(name: 'destination_account_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Account $destinationAccount;

    #[ORM\Column(type: Types::BIGINT)]
    private int $amount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::STRING, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\Column(type: Types::STRING, enumType: TransactionStatus::class)]
    private TransactionStatus $status;

    #[ORM\Column(type: Types::STRING, length: 128, nullable: true, unique: true)]
    private ?string $idempotencyKey;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $reference;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $sourceBalanceAfter;

    #[ORM\Column(type: Types::BIGINT, nullable: true)]
    private ?int $destinationBalanceAfter;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        ?Account $sourceAccount,
        ?Account $destinationAccount,
        Money $money,
        TransactionType $type,
        ?string $reference = null,
        ?string $idempotencyKey = null,
    ) {
        $this->id = Uuid::v7();
        $this->sourceAccount = $sourceAccount;
        $this->destinationAccount = $destinationAccount;
        $this->amount = $money->getCents();
        $this->currency = $money->getCurrency();
        $this->type = $type;
        $this->status = TransactionStatus::COMPLETED;
        $this->reference = $reference;
        $this->idempotencyKey = $idempotencyKey;
        $this->sourceBalanceAfter = $sourceAccount?->getBalance();
        $this->destinationBalanceAfter = $destinationAccount?->getBalance();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceAccount(): ?Account
    {
        return $this->sourceAccount;
    }

    public function getDestinationAccount(): ?Account
    {
        return $this->destinationAccount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getMoney(): Money
    {
        return Money::fromCents($this->amount, $this->currency);
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getStatus(): TransactionStatus
    {
        return $this->status;
    }

    public function setStatus(TransactionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getSourceBalanceAfter(): ?int
    {
        return $this->sourceBalanceAfter;
    }

    public function setSourceBalanceAfter(?int $balance): static
    {
        $this->sourceBalanceAfter = $balance;

        return $this;
    }

    public function getDestinationBalanceAfter(): ?int
    {
        return $this->destinationBalanceAfter;
    }

    public function setDestinationBalanceAfter(?int $balance): static
    {
        $this->destinationBalanceAfter = $balance;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
