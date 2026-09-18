<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Account\ValueObject\Money;
use App\Repository\OrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $buyer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $seller;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\OneToOne(targetEntity: Transaction::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Transaction $transaction;

    #[ORM\Column(type: Types::BIGINT)]
    private int $amount;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $buyer,
        User $seller,
        Product $product,
        Transaction $transaction,
        Money $amount,
    ) {
        $this->id = Uuid::v7();
        $this->buyer = $buyer;
        $this->seller = $seller;
        $this->product = $product;
        $this->transaction = $transaction;
        $this->amount = $amount->getCents();
        $this->currency = $amount->getCurrency();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBuyer(): User
    {
        return $this->buyer;
    }

    public function getSeller(): User
    {
        return $this->seller;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getTransaction(): Transaction
    {
        return $this->transaction;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
