<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Account\Command\DepositFundsCommand;
use App\Domain\Account\Command\TransferFundsCommand;
use App\Domain\Idempotency\Service\IdempotencyManager;
use App\Entity\Transaction;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/account', name: 'api_account_')]
class AccountController extends AbstractController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly AccountRepository $accountRepository,
        private readonly TransactionRepository $transactionRepository,
        private readonly IdempotencyManager $idempotencyManager,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $identifier = $request->query->get('email') ?? $request->query->get('account');
        if (null === $identifier || '' === trim($identifier)) {
            return $this->json(['error' => 'Provide ?email= or ?account= query parameter.'], Response::HTTP_BAD_REQUEST);
        }

        $account = $this->accountRepository->findByAccountNumber($identifier)
            ?? $this->accountRepository->findByEmail($identifier);

        if (null === $account) {
            return $this->json(['error' => sprintf('Account "%s" not found.', $identifier)], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $account->getId()->toRfc4122(),
            'accountNumber' => $account->getAccountNumber(),
            'email' => $account->getUser()->getEmail(),
            'balance' => $account->getBalance(),
            'balanceFormatted' => sprintf('$%.2f', $account->getBalance() / 100.0),
            'currency' => $account->getCurrency(),
        ]);
    }

    #[Route('/deposit', name: 'deposit', methods: ['POST'])]
    public function deposit(Request $request): Response
    {
        if ($cachedResponse = $this->idempotencyManager->processRequest($request)) {
            /* @var JsonResponse $cachedResponse */
            return $cachedResponse;
        }

        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $account = (string) ($data['account'] ?? '');
        $amount = (float) ($data['amount'] ?? 0);
        $reference = isset($data['reference']) ? (string) $data['reference'] : 'External Gateway Webhook';
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if ('' === $account || $amount <= 0) {
            return $this->json(['error' => 'Valid account and positive amount are required.'], Response::HTTP_BAD_REQUEST);
        }

        $amountCents = (int) round($amount * 100);

        /** @var Transaction $transaction */
        $transaction = $this->handle(new DepositFundsCommand(
            accountIdentifier: $account,
            amountCents: $amountCents,
            reference: $reference,
            idempotencyKey: $idempotencyKey
        ));

        $response = $this->json([
            'message' => 'Deposit successful.',
            'transaction' => [
                'id' => $transaction->getId()->toRfc4122(),
                'type' => $transaction->getType()->value,
                'status' => $transaction->getStatus()->value,
                'amount' => $transaction->getAmount(),
                'amountFormatted' => sprintf('$%.2f', $transaction->getAmount() / 100.0),
                'currency' => $transaction->getCurrency(),
                'destinationBalanceAfter' => $transaction->getDestinationBalanceAfter(),
                'reference' => $transaction->getReference(),
                'createdAt' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_CREATED);

        $this->idempotencyManager->recordResponse($request, $response);

        return $response;
    }

    #[Route('/transfer', name: 'transfer', methods: ['POST'])]
    public function transfer(Request $request): Response
    {
        if ($cachedResponse = $this->idempotencyManager->processRequest($request)) {
            /* @var JsonResponse $cachedResponse */
            return $cachedResponse;
        }

        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $from = (string) ($data['from'] ?? '');
        $to = (string) ($data['to'] ?? '');
        $amount = (float) ($data['amount'] ?? 0);
        $reference = isset($data['reference']) ? (string) $data['reference'] : null;
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if ('' === $from || '' === $to || $amount <= 0) {
            return $this->json([
                'error' => 'Missing required fields: from, to, positive amount.',
                'received' => $data,
            ], Response::HTTP_BAD_REQUEST);
        }

        $amountCents = (int) round($amount * 100);

        /** @var Transaction $transaction */
        $transaction = $this->handle(new TransferFundsCommand(
            sourceIdentifier: $from,
            destinationIdentifier: $to,
            amountCents: $amountCents,
            reference: $reference,
            idempotencyKey: $idempotencyKey
        ));

        $response = $this->json([
            'message' => 'Transfer completed successfully.',
            'transaction' => [
                'id' => $transaction->getId()->toRfc4122(),
                'type' => $transaction->getType()->value,
                'status' => $transaction->getStatus()->value,
                'amount' => $transaction->getAmount(),
                'amountFormatted' => sprintf('$%.2f', $transaction->getAmount() / 100.0),
                'sourceAccount' => $transaction->getSourceAccount()?->getAccountNumber(),
                'destinationAccount' => $transaction->getDestinationAccount()?->getAccountNumber(),
                'sourceBalanceAfter' => $transaction->getSourceBalanceAfter(),
                'destinationBalanceAfter' => $transaction->getDestinationBalanceAfter(),
                'reference' => $transaction->getReference(),
                'createdAt' => $transaction->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_OK);

        $this->idempotencyManager->recordResponse($request, $response);

        return $response;
    }

    #[Route('/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(Request $request): JsonResponse
    {
        $identifier = $request->query->get('account') ?? $request->query->get('email');
        if (null === $identifier || '' === trim($identifier)) {
            return $this->json(['error' => 'Provide ?account= or ?email= query parameter.'], Response::HTTP_BAD_REQUEST);
        }

        $account = $this->accountRepository->findByAccountNumber($identifier)
            ?? $this->accountRepository->findByEmail($identifier);

        if (null === $account) {
            return $this->json(['error' => 'Account not found.'], Response::HTTP_NOT_FOUND);
        }

        $limit = max(1, min(100, (int) $request->query->get('limit', 50)));
        $transactions = $this->transactionRepository->findForAccount($account, $limit);

        $data = array_map(static fn (Transaction $tx) => [
            'id' => $tx->getId()->toRfc4122(),
            'type' => $tx->getType()->value,
            'status' => $tx->getStatus()->value,
            'amount' => $tx->getAmount(),
            'amountFormatted' => sprintf('$%.2f', $tx->getAmount() / 100.0),
            'sourceAccount' => $tx->getSourceAccount()?->getAccountNumber(),
            'destinationAccount' => $tx->getDestinationAccount()?->getAccountNumber(),
            'reference' => $tx->getReference(),
            'createdAt' => $tx->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $transactions);

        return $this->json([
            'account' => $account->getAccountNumber(),
            'balance' => $account->getBalance(),
            'balanceFormatted' => sprintf('$%.2f', $account->getBalance() / 100.0),
            'total' => count($data),
            'transactions' => $data,
        ]);
    }
}
