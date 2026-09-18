<?php

declare(strict_types=1);

namespace App\Domain\Idempotency\Service;

use App\Domain\Idempotency\Exception\IdempotencyConflictException;
use App\Entity\IdempotencyRecord;
use App\Repository\IdempotencyRecordRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyManager
{
    public function __construct(
        private readonly IdempotencyRecordRepository $recordRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function processRequest(Request $request): ?Response
    {
        $key = $request->headers->get('Idempotency-Key');
        if (null === $key || '' === trim($key)) {
            return null;
        }

        $existing = $this->recordRepository->findByKey($key);
        if (null !== $existing) {
            if (IdempotencyRecord::STATUS_PROCESSING === $existing->getStatus()) {
                throw new IdempotencyConflictException($key);
            }

            if (IdempotencyRecord::STATUS_COMPLETED === $existing->getStatus()) {
                return new JsonResponse(
                    $existing->getResponseBody(),
                    $existing->getResponseCode() ?? 200,
                    ['X-Idempotent-Replayed' => 'true'],
                    true
                );
            }
        }

        $hash = hash('sha256', $request->getContent());
        $record = new IdempotencyRecord($key, $hash);
        $this->em->persist($record);
        $this->em->flush();

        return null;
    }

    public function recordResponse(Request $request, Response $response): void
    {
        $key = $request->headers->get('Idempotency-Key');
        if (null === $key || '' === trim($key)) {
            return;
        }

        $record = $this->recordRepository->findByKey($key);
        if (null !== $record && IdempotencyRecord::STATUS_PROCESSING === $record->getStatus()) {
            $record->complete(
                $response->getStatusCode(),
                (string) $response->getContent()
            );
            $this->em->flush();
        }
    }
}
