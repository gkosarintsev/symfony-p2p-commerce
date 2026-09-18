<?php

declare(strict_types=1);

namespace App\Domain\Ledger\MessageHandler;

use App\Domain\Ledger\Message\GenerateReceiptPdfMessage;
use App\Domain\Ledger\Service\ReceiptPdfGenerator;
use App\Repository\TransactionRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class GenerateReceiptPdfHandler
{
    public function __construct(
        private TransactionRepository $transactionRepository,
        private ReceiptPdfGenerator $pdfGenerator,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateReceiptPdfMessage $message): void
    {
        $transaction = $this->transactionRepository->find(Uuid::fromString($message->transactionId));
        if (null === $transaction) {
            $this->logger->warning('Transaction not found for PDF receipt generation', [
                'transaction_id' => $message->transactionId,
            ]);

            return;
        }

        $path = $this->pdfGenerator->generate($transaction);
        $this->logger->info('PDF Receipt generated successfully', [
            'transaction_id' => $message->transactionId,
            'file' => $path,
        ]);
    }
}
