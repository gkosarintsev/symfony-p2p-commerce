<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Account\Service\AccountNumberGenerator;
use App\Domain\Account\ValueObject\Money;
use App\Domain\Product\Command\PurchaseProductCommand;
use App\Domain\Product\Exception\ProductAlreadySoldException;
use App\Entity\Account;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Enum\ProductStatus;
use App\Enum\TransactionType;
use App\Repository\AccountRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ProductPurchaseTest extends KernelTestCase
{
    use HandleTrait;

    private EntityManagerInterface $em;
    private AccountRepository $accountRepository;
    private ProductRepository $productRepository;
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

        /** @var ProductRepository $productRepository */
        $productRepository = $container->get(ProductRepository::class);
        $this->productRepository = $productRepository;

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

    private function createUserWithAccount(string $email, int $initialBalanceCents): User
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

        return $user;
    }

    public function testAtomicPurchaseTransfersFundsAndUpdatesProductOwnership(): void
    {
        $unique = bin2hex(random_bytes(4));
        $buyer = $this->createUserWithAccount("buyer_{$unique}@example.com", 30000); // $300.00
        $seller = $this->createUserWithAccount("seller_{$unique}@example.com", 5000); // $50.00

        $product = new Product(
            seller: $seller,
            title: '4K Action Camera',
            description: 'Waterproof 4K 60fps action camera with accessories.',
            price: Money::fromCents(15000), // $150.00
            category: 'Cameras'
        );
        $this->em->persist($product);
        $this->em->flush();

        /** @var Order $order */
        $order = $this->handle(new PurchaseProductCommand(
            buyerEmail: $buyer->getEmail(),
            productId: $product->getId()->toRfc4122()
        ));

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(15000, $order->getAmount());
        $this->assertSame(TransactionType::PURCHASE, $order->getTransaction()->getType());

        // Refresh state from database
        $this->em->clear();
        $reloadedProduct = $this->productRepository->find($product->getId());
        $buyerAccount = $buyer->getAccount();
        $sellerAccount = $seller->getAccount();

        $this->assertNotNull($buyerAccount);
        $this->assertNotNull($sellerAccount);

        $reloadedBuyerAccount = $this->accountRepository->find($buyerAccount->getId());
        $reloadedSellerAccount = $this->accountRepository->find($sellerAccount->getId());

        $this->assertNotNull($reloadedProduct);
        $this->assertSame(ProductStatus::SOLD, $reloadedProduct->getStatus());
        $this->assertSame($buyer->getId()->toRfc4122(), $reloadedProduct->getOwner()?->getId()->toRfc4122());

        // Verify balances: buyer deducted 15000, seller credited 15000
        $this->assertNotNull($reloadedBuyerAccount);
        $this->assertNotNull($reloadedSellerAccount);
        $this->assertSame(15000, $reloadedBuyerAccount->getBalance()); // $300 - $150 = $150
        $this->assertSame(20000, $reloadedSellerAccount->getBalance()); // $50 + $150 = $200
    }

    public function testBuyingAlreadySoldProductThrowsException(): void
    {
        $unique = bin2hex(random_bytes(4));
        $buyer1 = $this->createUserWithAccount("b1_{$unique}@example.com", 50000);
        $buyer2 = $this->createUserWithAccount("b2_{$unique}@example.com", 50000);
        $seller = $this->createUserWithAccount("s_{$unique}@example.com", 1000);

        $product = new Product(
            seller: $seller,
            title: 'Rare Vinyl Record',
            description: 'Original 1969 pressing.',
            price: Money::fromCents(8000),
            category: 'Music'
        );
        $this->em->persist($product);
        $this->em->flush();

        // First purchase succeeds
        $this->handle(new PurchaseProductCommand(
            buyerEmail: $buyer1->getEmail(),
            productId: $product->getId()->toRfc4122()
        ));

        // Second purchase must fail
        try {
            $this->handle(new PurchaseProductCommand(
                buyerEmail: $buyer2->getEmail(),
                productId: $product->getId()->toRfc4122()
            ));
            $this->fail('Expected ProductAlreadySoldException was not thrown.');
        } catch (\Throwable $e) {
            $actual = $e instanceof HandlerFailedException && $e->getPrevious() ? $e->getPrevious() : $e;
            $this->assertInstanceOf(ProductAlreadySoldException::class, $actual);
        }
    }
}
