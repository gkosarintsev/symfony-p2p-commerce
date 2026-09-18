<?php

declare(strict_types=1);

namespace App\Domain\Account\CommandHandler;

use App\Domain\Account\Command\TransferFundsCommand;
use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\ValueObject\Money;
use App\Domain\Ledger\Service\LedgerService;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use App\Repository\AccountRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class TransferFundsHandler
{
    public function __construct(
        private AccountRepository $accountRepository,
        private LedgerService $ledgerService,
    ) {
    }

    public function __invoke(TransferFundsCommand $command): Transaction
    {
        // Resolve source account
        $source = $this->accountRepository->findByAccountNumber($command->sourceIdentifier);
        if (null === $source) {
            $source = $this->accountRepository->findByEmail($command->sourceIdentifier);
        }
        if (null === $source) {
            throw new AccountNotFoundException($command->sourceIdentifier);
        }

        // Resolve destination account
        $destination = $this->accountRepository->findByAccountNumber($command->destinationIdentifier);
        if (null === $destination) {
            $destination = $this->accountRepository->findByEmail($command->destinationIdentifier);
        }
        if (null === $destination) {
            throw new AccountNotFoundException($command->destinationIdentifier);
        }

        $money = Money::fromCents($command->amountCents);

        return $this->ledgerService->transfer(
            source: $source,
            destination: $destination,
            amount: $money,
            reference: $command->reference,
            idempotencyKey: $command->idempotencyKey,
            type: TransactionType::TRANSFER
        );
    }
}
