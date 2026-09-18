<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Ledger\Service\ReceiptPdfGenerator;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[Route('/api/v1/transactions', name: 'api_transactions_')]
class TransactionController extends AbstractController
{
    #[Route('/{id}/receipt', name: 'receipt', methods: ['GET'])]
    public function receipt(
        string $id,
        TransactionRepository $transactionRepository,
        ReceiptPdfGenerator $pdfGenerator,
    ): Response {
        $transaction = $transactionRepository->find(Uuid::fromString($id));
        if (null === $transaction) {
            return new JsonResponse(['error' => 'Transaction not found.'], Response::HTTP_NOT_FOUND);
        }

        $filePath = $pdfGenerator->generate($transaction);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('receipt_%s.pdf', $id)
        );
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
}
