<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Idempotency\Service\IdempotencyManager;
use App\Domain\Product\Command\CreateProductCommand;
use App\Domain\Product\Command\PurchaseProductCommand;
use App\Entity\Order;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/products', name: 'api_products_')]
class ProductController extends AbstractController
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $commandBus,
        private readonly ProductRepository $productRepository,
        private readonly IdempotencyManager $idempotencyManager,
    ) {
        $this->messageBus = $commandBus;
    }

    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $category = $request->query->get('category');
        $products = $this->productRepository->findActiveProducts($category);

        $items = array_map(static fn (Product $p) => [
            'id' => $p->getId()->toRfc4122(),
            'title' => $p->getTitle(),
            'description' => $p->getDescription(),
            'price' => $p->getPrice(),
            'priceFormatted' => sprintf('$%.2f', $p->getPrice() / 100.0),
            'currency' => $p->getCurrency(),
            'status' => $p->getStatus()->value,
            'category' => $p->getCategory(),
            'seller' => [
                'email' => $p->getSeller()->getEmail(),
                'accountNumber' => $p->getSeller()->getAccount()?->getAccountNumber(),
            ],
            'createdAt' => $p->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ], $products);

        return $this->json([
            'total' => count($items),
            'products' => $items,
        ]);
    }

    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $sellerEmail = trim((string) ($data['sellerEmail'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $description = trim((string) ($data['description'] ?? ''));
        $price = (float) ($data['price'] ?? 0);
        $category = trim((string) ($data['category'] ?? 'General'));

        if ('' === $sellerEmail || '' === $title || $price <= 0) {
            return $this->json(['error' => 'Missing sellerEmail, title, or valid price.'], Response::HTTP_BAD_REQUEST);
        }

        $priceCents = (int) round($price * 100);

        /** @var Product $product */
        $product = $this->handle(new CreateProductCommand(
            sellerEmail: $sellerEmail,
            title: $title,
            description: $description,
            priceCents: $priceCents,
            category: $category
        ));

        return $this->json([
            'message' => 'Product listed successfully.',
            'product' => [
                'id' => $product->getId()->toRfc4122(),
                'title' => $product->getTitle(),
                'price' => $product->getPrice(),
                'priceFormatted' => sprintf('$%.2f', $product->getPrice() / 100.0),
                'status' => $product->getStatus()->value,
            ],
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}/buy', name: 'buy', methods: ['POST'])]
    public function buy(string $id, Request $request): Response
    {
        if ($cachedResponse = $this->idempotencyManager->processRequest($request)) {
            /* @var JsonResponse $cachedResponse */
            return $cachedResponse;
        }

        $data = $request->getPayload()->all();
        if (empty($data)) {
            $data = json_decode($request->getContent(), true) ?? [];
        }

        $buyerEmail = trim((string) ($data['buyerEmail'] ?? ''));
        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if ('' === $buyerEmail) {
            return $this->json(['error' => 'buyerEmail is required.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var Order $order */
        $order = $this->handle(new PurchaseProductCommand(
            buyerEmail: $buyerEmail,
            productId: $id,
            idempotencyKey: $idempotencyKey
        ));

        $response = $this->json([
            'message' => 'Product purchased successfully!',
            'order' => [
                'id' => $order->getId()->toRfc4122(),
                'productTitle' => $order->getProduct()->getTitle(),
                'amount' => $order->getAmount(),
                'amountFormatted' => sprintf('$%.2f', $order->getAmount() / 100.0),
                'buyer' => $order->getBuyer()->getEmail(),
                'seller' => $order->getSeller()->getEmail(),
                'transactionId' => $order->getTransaction()->getId()->toRfc4122(),
                'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            ],
        ], Response::HTTP_CREATED);

        $this->idempotencyManager->recordResponse($request, $response);

        return $response;
    }
}
