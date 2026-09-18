<?php

declare(strict_types=1);

namespace App\Domain\Product\CommandHandler;

use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\Exception\SelfTransferException;
use App\Domain\Ledger\Service\LedgerService;
use App\Domain\Notification\Message\SendPurchaseNotificationMessage;
use App\Domain\Product\Command\PurchaseProductCommand;
use App\Domain\Product\Event\ProductPurchasedEvent;
use App\Domain\Product\Exception\ProductAlreadySoldException;
use App\Domain\Product\Exception\ProductNotActiveException;
use App\Domain\Product\Exception\ProductNotFoundException;
use App\Entity\Order;
use App\Enum\ProductStatus;
use App\Enum\TransactionType;
use App\Repository\ProductRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class PurchaseProductHandler
{
    public function __construct(
        private ProductRepository $productRepository,
        private UserRepository $userRepository,
        private LedgerService $ledgerService,
        private EntityManagerInterface $em,
        private MessageBusInterface $eventBus,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(PurchaseProductCommand $command): Order
    {
        $product = $this->productRepository->find(Uuid::fromString($command->productId));
        if (null === $product) {
            throw new ProductNotFoundException($command->productId);
        }

        if (ProductStatus::SOLD === $product->getStatus()) {
            throw new ProductAlreadySoldException($command->productId);
        }

        if (ProductStatus::ACTIVE !== $product->getStatus()) {
            throw new ProductNotActiveException($command->productId);
        }

        $buyer = $this->userRepository->findOneBy(['email' => $command->buyerEmail]);
        if (null === $buyer) {
            throw new AccountNotFoundException($command->buyerEmail);
        }

        $seller = $product->getSeller();
        if ($buyer->getId()->equals($seller->getId())) {
            throw new SelfTransferException();
        }

        $buyerAccount = $buyer->getAccount();
        $sellerAccount = $seller->getAccount();

        if (null === $buyerAccount || null === $sellerAccount) {
            throw new AccountNotFoundException('Wallet not found for buyer or seller');
        }

        // Execute atomic funds transfer from buyer to seller via LedgerService
        $reference = sprintf('Purchase product: %s (#%s)', $product->getTitle(), $product->getId()->toRfc4122());
        $transaction = $this->ledgerService->transfer(
            source: $buyerAccount,
            destination: $sellerAccount,
            amount: $product->getMoney(),
            reference: $reference,
            idempotencyKey: $command->idempotencyKey,
            type: TransactionType::PURCHASE
        );

        // Update Product status and ownership
        $product->setStatus(ProductStatus::SOLD);
        $product->setOwner($buyer);

        // Create Order record
        $order = new Order(
            buyer: $buyer,
            seller: $seller,
            product: $product,
            transaction: $transaction,
            amount: $product->getMoney()
        );

        $this->em->persist($order);
        $this->em->flush();

        // Dispatch domain event
        $this->eventBus->dispatch(new ProductPurchasedEvent($order));

        // Dispatch async email notification message to worker queue
        $this->messageBus->dispatch(new SendPurchaseNotificationMessage(
            orderId: $order->getId()->toRfc4122(),
            buyerEmail: $buyer->getEmail(),
            sellerEmail: $seller->getEmail(),
            productTitle: $product->getTitle(),
            amountCents: $product->getPrice()
        ));

        return $order;
    }
}
