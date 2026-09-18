<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Account\Command\DepositFundsCommand;
use App\Domain\Account\Command\TransferFundsCommand;
use App\Domain\Ledger\Service\ReceiptPdfGenerator;
use App\Domain\Product\Command\CreateProductCommand;
use App\Domain\Product\Command\PurchaseProductCommand;
use App\Entity\User;
use App\Repository\ProductRepository;
use App\Repository\TransactionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

class HomeController extends AbstractController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly TransactionRepository $transactionRepository,
        private readonly ProductRepository $productRepository,
        private readonly ReceiptPdfGenerator $pdfGenerator,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        $products = $this->productRepository->findActiveProducts();

        return $this->render('home/index.html.twig', [
            'products' => $products,
        ]);
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_USER')]
    public function dashboard(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $user->getAccount();

        $transactions = [];
        if (null !== $account) {
            $transactions = $this->transactionRepository->findForAccount($account, 20);
        }

        $activeProducts = $this->productRepository->findActiveProducts();

        return $this->render('home/dashboard.html.twig', [
            'user' => $user,
            'account' => $account,
            'transactions' => $transactions,
            'products' => $activeProducts,
        ]);
    }

    #[Route('/transfer', name: 'app_transfer', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function transfer(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $user->getAccount();

        if (null === $account) {
            $this->addFlash('error', 'User does not have an active account.');

            return $this->redirectToRoute('app_dashboard');
        }

        $recipient = trim((string) $request->request->get('recipient', ''));
        $amount = (float) $request->request->get('amount', 0);
        $reference = trim((string) $request->request->get('reference', ''));

        if ('' === $recipient || $amount <= 0) {
            $this->addFlash('error', 'Please enter a valid recipient and positive amount.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $this->handle(new TransferFundsCommand(
                sourceIdentifier: $account->getAccountNumber(),
                destinationIdentifier: $recipient,
                amountCents: (int) round($amount * 100),
                reference: '' !== $reference ? $reference : null
            ));
            $this->addFlash('success', sprintf('Successfully transferred $%.2f to %s!', $amount, $recipient));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/deposit', name: 'app_deposit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function deposit(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $account = $user->getAccount();

        if (null === $account) {
            $this->addFlash('error', 'User does not have an active account.');

            return $this->redirectToRoute('app_dashboard');
        }

        $amount = (float) $request->request->get('amount', 0);
        if ($amount <= 0) {
            $this->addFlash('error', 'Deposit amount must be greater than 0.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $this->handle(new DepositFundsCommand(
                accountIdentifier: $account->getAccountNumber(),
                amountCents: (int) round($amount * 100),
                reference: 'Simulated Card Top-up'
            ));
            $this->addFlash('success', sprintf('Successfully deposited $%.2f to your account!', $amount));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/marketplace/buy/{id}', name: 'app_marketplace_buy', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function buyProduct(string $id): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $this->handle(new PurchaseProductCommand(
                buyerEmail: $user->getEmail(),
                productId: $id
            ));
            $this->addFlash('success', 'Purchase completed successfully! Funds transferred and order confirmed.');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/marketplace/create', name: 'app_marketplace_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createProduct(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $title = trim((string) $request->request->get('title', ''));
        $description = trim((string) $request->request->get('description', ''));
        $price = (float) $request->request->get('price', 0);
        $category = trim((string) $request->request->get('category', 'General'));

        if ('' === $title || $price <= 0) {
            $this->addFlash('error', 'Title and a positive price are required.');

            return $this->redirectToRoute('app_dashboard');
        }

        try {
            $this->handle(new CreateProductCommand(
                sellerEmail: $user->getEmail(),
                title: $title,
                description: $description,
                priceCents: (int) round($price * 100),
                category: $category
            ));
            $this->addFlash('success', 'Product listed on the marketplace successfully!');
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/receipt/{id}', name: 'app_receipt', methods: ['GET'])]
    public function downloadReceipt(string $id): Response
    {
        $transaction = $this->transactionRepository->find(Uuid::fromString($id));
        if (null === $transaction) {
            throw $this->createNotFoundException('Transaction not found');
        }

        $filePath = $this->pdfGenerator->generate($transaction);

        $response = new BinaryFileResponse($filePath);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('receipt_%s.pdf', $id)
        );
        $response->headers->set('Content-Type', 'application/pdf');

        return $response;
    }
}
