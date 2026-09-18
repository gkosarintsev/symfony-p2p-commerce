<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Account\Command\DepositFundsCommand;
use App\Domain\Account\Command\TransferFundsCommand;
use App\Domain\Account\Exception\InsufficientFundsException;
use App\Domain\Account\Exception\InvalidAmountException;
use App\Domain\Account\Exception\SelfTransferException;
use App\Domain\Account\Service\AccountNumberGenerator;
use App\Domain\Account\ValueObject\Money;
use App\Entity\Account;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\TransactionStatus;
use App\Enum\TransactionType;
use App\Repository\AccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LedgerTransferTest extends KernelTestCase
{
    use HandleTrait;

    private EntityManagerInterface $em;
    private AccountRepository $accountRepository;
    private AccountNumberGenerator $accountNumberGenerator;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var AccountRepository $accountRepository */
        $accountRepository = $container->get(AccountRepository::class);
        $this->accountRepository = $accountRepository;

        /** @var AccountNumberGenerator $accountNumberGenerator */
        $accountNumberGenerator = $container->get(AccountNumberGenerator::class);
        $this->accountNumberGenerator = $accountNumberGenerator;

        /** @var UserPasswordHasherInterface $passwordHasher */
        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        $this->passwordHasher = $passwordHasher;

        /** @var MessageBusInterface $commandBus */
        $commandBus = $container->get('command.bus');
        $this->messageBus = $commandBus;
    }

    private function createUserWithAccount(string $email, int $initialBalanceCents): Account
    {
        $user = new User($email);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
        $accNumber = $this->accountNumberGenerator->generateUnique();
        $account = new Account($user, $accNumber->getValue());
        if ($initialBalanceCents > 0) {
            $account->credit(Money::fromCents($initialBalanceCents));
        }
        $user->setAccount($account);

        $this->em->persist($user);
        $this->em->persist($account);
        $this->em->flush();

        return $account;
    }

    public function testSuccessfulTransferUpdatesBalancesAndCreatesLedgerRecord(): void
    {
        $unique = bin2hex(random_bytes(4));
        $senderAccount = $this->createUserWithAccount("sender_{$unique}@example.com", 20000); // $200.00
        $recipientAccount = $this->createUserWithAccount("recipient_{$unique}@example.com", 5000); // $50.00

        /** @var Transaction $tx */
        $tx = $this->handle(new TransferFundsCommand(
            sourceIdentifier: $senderAccount->getAccountNumber(),
            destinationIdentifier: $recipientAccount->getAccountNumber(),
            amountCents: 7500, // $75.00
            reference: 'Test milestone payout'
        ));

        $this->assertInstanceOf(Transaction::class, $tx);
        $this->assertSame(TransactionType::TRANSFER, $tx->getType());
        $this->assertSame(TransactionStatus::COMPLETED, $tx->getStatus());
        $this->assertSame(7500, $tx->getAmount());

        // Refresh accounts from DB
        $this->em->clear();
        $reloadedSender = $this->accountRepository->find($senderAccount->getId());
        $reloadedRecipient = $this->accountRepository->find($recipientAccount->getId());

        $this->assertNotNull($reloadedSender);
        $this->assertNotNull($reloadedRecipient);

        $this->assertSame(12500, $reloadedSender->getBalance()); // $200 - $75 = $125
        $this->assertSame(12500, $reloadedRecipient->getBalance()); // $50 + $75 = $125
        $this->assertSame(12500, $tx->getSourceBalanceAfter());
        $this->assertSame(12500, $tx->getDestinationBalanceAfter());
    }

    public function testInsufficientFundsThrowsExceptionAndRollsBack(): void
    {
        $unique = bin2hex(random_bytes(4));
        $sender = $this->createUserWithAccount("poor_{$unique}@example.com", 1000); // $10.00
        $recipient = $this->createUserWithAccount("rich_{$unique}@example.com", 5000);

        try {
            $this->handle(new TransferFundsCommand(
                sourceIdentifier: $sender->getAccountNumber(),
                destinationIdentifier: $recipient->getAccountNumber(),
                amountCents: 5000 // Attempting to send $50.00 with only $10.00
            ));
            $this->fail('Expected InsufficientFundsException was not thrown.');
        } catch (\Throwable $e) {
            $actual = $e instanceof HandlerFailedException && $e->getPrevious() ? $e->getPrevious() : $e;
            $this->assertInstanceOf(InsufficientFundsException::class, $actual);
        }

        // Verify balance was not touched
        $this->em->clear();
        $reloaded = $this->accountRepository->find($sender->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(1000, $reloaded->getBalance());
    }

    public function testSelfTransferThrowsException(): void
    {
        $unique = bin2hex(random_bytes(4));
        $user = $this->createUserWithAccount("self_{$unique}@example.com", 10000);

        try {
            $this->handle(new TransferFundsCommand(
                sourceIdentifier: $user->getAccountNumber(),
                destinationIdentifier: $user->getAccountNumber(),
                amountCents: 1000
            ));
            $this->fail('Expected SelfTransferException was not thrown.');
        } catch (\Throwable $e) {
            $actual = $e instanceof HandlerFailedException && $e->getPrevious() ? $e->getPrevious() : $e;
            $this->assertInstanceOf(SelfTransferException::class, $actual);
        }
    }

    public function testZeroOrNegativeAmountThrowsException(): void
    {
        $unique = bin2hex(random_bytes(4));
        $sender = $this->createUserWithAccount("s1_{$unique}@example.com", 10000);
        $recipient = $this->createUserWithAccount("r1_{$unique}@example.com", 10000);

        try {
            $this->handle(new TransferFundsCommand(
                sourceIdentifier: $sender->getAccountNumber(),
                destinationIdentifier: $recipient->getAccountNumber(),
                amountCents: 0
            ));
            $this->fail('Expected InvalidAmountException was not thrown.');
        } catch (\Throwable $e) {
            $actual = $e instanceof HandlerFailedException && $e->getPrevious() ? $e->getPrevious() : $e;
            $this->assertInstanceOf(InvalidAmountException::class, $actual);
        }
    }

    public function testDepositFundsCommandIncreasesBalanceAndCreatesLedgerRecord(): void
    {
        $unique = bin2hex(random_bytes(4));
        $account = $this->createUserWithAccount("deposit_{$unique}@example.com", 1000);

        /** @var Transaction $tx */
        $tx = $this->handle(new DepositFundsCommand(
            accountIdentifier: $account->getAccountNumber(),
            amountCents: 15000, // $150.00
            reference: 'Stripe Webhook Simulation'
        ));

        $this->assertSame(TransactionType::DEPOSIT, $tx->getType());
        $this->assertSame(15000, $tx->getAmount());

        $this->em->clear();
        $reloaded = $this->accountRepository->find($account->getId());
        $this->assertNotNull($reloaded);
        $this->assertSame(16000, $reloaded->getBalance()); // $10 + $150 = $160
    }
}
