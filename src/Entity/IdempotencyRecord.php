<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\IdempotencyRecordRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: IdempotencyRecordRepository::class)]
#[ORM\Table(name: 'idempotency_records')]
#[ORM\Index(name: 'idx_idempotency_key', columns: ['key'])]
class IdempotencyRecord
{
    public const string STATUS_PROCESSING = 'PROCESSING';
    public const string STATUS_COMPLETED = 'COMPLETED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private Uuid $id;

    #[ORM\Column(type: Types::STRING, length: 128, unique: true)]
    private string $key;

    #[ORM\Column(type: Types::STRING, length: 64)]
    private string $requestHash;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $responseCode = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(string $key, string $requestHash)
    {
        $this->id = Uuid::v7();
        $this->key = $key;
        $this->requestHash = $requestHash;
        $this->status = self::STATUS_PROCESSING;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getRequestHash(): string
    {
        return $this->requestHash;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResponseCode(): ?int
    {
        return $this->responseCode;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function complete(int $responseCode, string $responseBody): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->responseCode = $responseCode;
        $this->responseBody = $responseBody;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
