<?php

declare(strict_types=1);

namespace App\Domain\Account\CommandHandler;

use App\Domain\Account\Command\DepositFundsCommand;
use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\ValueObject\Money;
use App\Domain\Ledger\Service\LedgerService;
use App\Entity\Transaction;
use App\Repository\AccountRepository;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class DepositFundsHandler
{
    public function __construct(
        private AccountRepository $accountRepository,
        private LedgerService $ledgerService,
    ) {
    }

    public function __invoke(DepositFundsCommand $command): Transaction
    {
        $account = $this->accountRepository->findByAccountNumber($command->accountIdentifier);
        if (null === $account) {
            $account = $this->accountRepository->findByEmail($command->accountIdentifier);
        }

        if (null === $account) {
            throw new AccountNotFoundException($command->accountIdentifier);
        }

        $money = Money::fromCents($command->amountCents);

        return $this->ledgerService->deposit(
            account: $account,
            amount: $money,
            reference: $command->reference,
            idempotencyKey: $command->idempotencyKey
        );
    }
}
