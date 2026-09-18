<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Service;

use App\Domain\Account\Event\FundsDepositedEvent;
use App\Domain\Account\Event\FundsTransferredEvent;
use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\Exception\InvalidAmountException;
use App\Domain\Account\Exception\SelfTransferException;
use App\Domain\Account\ValueObject\Money;
use App\Domain\Ledger\Message\GenerateReceiptPdfMessage;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Enum\TransactionType;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

class LedgerService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $eventBus,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function deposit(
        Account $account,
        Money $amount,
        ?string $reference = null,
        ?string $idempotencyKey = null,
    ): Transaction {
        if (!$amount->isPositive()) {
            throw new InvalidAmountException('Deposit amount must be strictly greater than 0.');
        }

        // Acquire pessimistic write lock on the receiving account
        /** @var Account|null $lockedAccount */
        $lockedAccount = $this->em->find(Account::class, $account->getId(), LockMode::PESSIMISTIC_WRITE);
        if (null === $lockedAccount) {
            throw new AccountNotFoundException($account->getAccountNumber());
        }

        $lockedAccount->credit($amount);

        $transaction = new Transaction(
            sourceAccount: null,
            destinationAccount: $lockedAccount,
            money: $amount,
            type: TransactionType::DEPOSIT,
            reference: $reference ?? 'Account Deposit',
            idempotencyKey: $idempotencyKey
        );
        $transaction->setDestinationBalanceAfter($lockedAccount->getBalance());

        $this->em->persist($transaction);
        $this->em->flush();

        $this->eventBus->dispatch(new FundsDepositedEvent($transaction));

        // Trigger async PDF receipt generation
        $this->messageBus->dispatch(new GenerateReceiptPdfMessage($transaction->getId()->toRfc4122()));

        return $transaction;
    }

    public function transfer(
        Account $source,
        Account $destination,
        Money $amount,
        ?string $reference = null,
        ?string $idempotencyKey = null,
        TransactionType $type = TransactionType::TRANSFER,
    ): Transaction {
        if ($source->getId()->equals($destination->getId())) {
            throw new SelfTransferException();
        }

        if (!$amount->isPositive()) {
            throw new InvalidAmountException('Transfer amount must be strictly greater than 0.');
        }

        // Deadlock prevention: sort accounts by UUID string to acquire locks in deterministic order
        $id1 = $source->getId()->toRfc4122();
        $id2 = $destination->getId()->toRfc4122();

        if (strcmp($id1, $id2) < 0) {
            /** @var Account|null $lockedSource */
            $lockedSource = $this->em->find(Account::class, $source->getId(), LockMode::PESSIMISTIC_WRITE);
            /** @var Account|null $lockedDestination */
            $lockedDestination = $this->em->find(Account::class, $destination->getId(), LockMode::PESSIMISTIC_WRITE);
        } else {
            /** @var Account|null $lockedDestination */
            $lockedDestination = $this->em->find(Account::class, $destination->getId(), LockMode::PESSIMISTIC_WRITE);
            /** @var Account|null $lockedSource */
            $lockedSource = $this->em->find(Account::class, $source->getId(), LockMode::PESSIMISTIC_WRITE);
        }

        if (null === $lockedSource) {
            throw new AccountNotFoundException($source->getAccountNumber());
        }
        if (null === $lockedDestination) {
            throw new AccountNotFoundException($destination->getAccountNumber());
        }

        // Debit sender (throws InsufficientFundsException if balance is insufficient)
        $lockedSource->debit($amount);

        // Credit recipient
        $lockedDestination->credit($amount);

        $transaction = new Transaction(
            sourceAccount: $lockedSource,
            destinationAccount: $lockedDestination,
            money: $amount,
            type: $type,
            reference: $reference ?? sprintf('Transfer to %s', $lockedDestination->getAccountNumber()),
            idempotencyKey: $idempotencyKey
        );
        $transaction->setSourceBalanceAfter($lockedSource->getBalance());
        $transaction->setDestinationBalanceAfter($lockedDestination->getBalance());

        $this->em->persist($transaction);
        $this->em->flush();

        $this->eventBus->dispatch(new FundsTransferredEvent($transaction));

        // Trigger async PDF receipt generation
        $this->messageBus->dispatch(new GenerateReceiptPdfMessage($transaction->getId()->toRfc4122()));

        return $transaction;
    }
}
