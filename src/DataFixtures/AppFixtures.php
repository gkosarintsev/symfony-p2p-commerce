<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Domain\Account\ValueObject\Money;
use App\Entity\Account;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\ProductStatus;
use App\Enum\TransactionType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // 1. Create Admin
        $admin = new User('admin@example.com');
        $admin->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $adminAccount = new Account($admin, 'ECOMM-0000-0000-0001');
        $adminAccount->credit(Money::fromCents(1000000)); // $10,000.00
        $admin->setAccount($adminAccount);

        $manager->persist($admin);
        $manager->persist($adminAccount);

        $adminDeposit = new Transaction(
            sourceAccount: null,
            destinationAccount: $adminAccount,
            money: Money::fromCents(1000000),
            type: TransactionType::DEPOSIT,
            reference: 'Initial system liquidity deposit'
        );
        $adminDeposit->setDestinationBalanceAfter(1000000);
        $manager->persist($adminDeposit);

        // 2. Create Alice
        $alice = new User('alice@example.com');
        $alice->setPassword($this->passwordHasher->hashPassword($alice, 'password123'));
        $aliceAccount = new Account($alice, 'ECOMM-1111-2222-3333');
        $aliceAccount->credit(Money::fromCents(150000)); // $1,500.00
        $alice->setAccount($aliceAccount);

        $manager->persist($alice);
        $manager->persist($aliceAccount);

        $aliceDeposit = new Transaction(
            sourceAccount: null,
            destinationAccount: $aliceAccount,
            money: Money::fromCents(150000),
            type: TransactionType::DEPOSIT,
            reference: 'Stripe External Webhook #wh_alice_01'
        );
        $aliceDeposit->setDestinationBalanceAfter(150000);
        $manager->persist($aliceDeposit);

        // 3. Create Bob
        $bob = new User('bob@example.com');
        $bob->setPassword($this->passwordHasher->hashPassword($bob, 'password123'));
        $bobAccount = new Account($bob, 'ECOMM-4444-5555-6666');
        $bobAccount->credit(Money::fromCents(50000)); // $500.00
        $bob->setAccount($bobAccount);

        $manager->persist($bob);
        $manager->persist($bobAccount);

        $bobDeposit = new Transaction(
            sourceAccount: null,
            destinationAccount: $bobAccount,
            money: Money::fromCents(50000),
            type: TransactionType::DEPOSIT,
            reference: 'PayPal External Webhook #pp_bob_01'
        );
        $bobDeposit->setDestinationBalanceAfter(50000);
        $manager->persist($bobDeposit);

        // 4. Create Charlie
        $charlie = new User('charlie@example.com');
        $charlie->setPassword($this->passwordHasher->hashPassword($charlie, 'password123'));
        $charlieAccount = new Account($charlie, 'ECOMM-7777-8888-9999');
        $charlieAccount->credit(Money::fromCents(25000)); // $250.00
        $charlie->setAccount($charlieAccount);

        $manager->persist($charlie);
        $manager->persist($charlieAccount);

        $charlieDeposit = new Transaction(
            sourceAccount: null,
            destinationAccount: $charlieAccount,
            money: Money::fromCents(25000),
            type: TransactionType::DEPOSIT,
            reference: 'Initial Welcome Bonus'
        );
        $charlieDeposit->setDestinationBalanceAfter(25000);
        $manager->persist($charlieDeposit);

        // 5. Products
        $p1 = new Product(
            seller: $bob,
            title: 'Mechanical Keyboard Custom RGB (Gateron Yellow)',
            description: 'Hot-swappable custom 75% mechanical keyboard with factory lubricated switches and PBT keycaps.',
            price: Money::fromCents(12000),
            category: 'Electronics'
        );
        $manager->persist($p1);

        $p2 = new Product(
            seller: $bob,
            title: 'Sony WH-1000XM5 Wireless Noise-Canceling',
            description: 'Industry-leading noise cancellation headphones, black color, flawless condition with original case.',
            price: Money::fromCents(29900),
            category: 'Audio'
        );
        $manager->persist($p2);

        $p3 = new Product(
            seller: $alice,
            title: 'Ultra-wide 34" IPS Curved Gaming Monitor',
            description: '144Hz 1ms response time, 3440x1440 resolution, HDR400, USB-C 90W power delivery.',
            price: Money::fromCents(45000),
            category: 'Monitors'
        );
        $manager->persist($p3);

        $p4 = new Product(
            seller: $charlie,
            title: 'Ergonomic Mesh Office Chair',
            description: 'Adjustable lumbar support, 3D armrests, breathable Korean mesh, pneumatic height adjustment.',
            price: Money::fromCents(18000),
            category: 'Furniture'
        );
        $manager->persist($p4);

        $p5 = new Product(
            seller: $alice,
            title: 'Apple MacBook Pro 16" M3 Max (36GB / 1TB)',
            description: 'Space Black, battery health 100%, includes 140W MagSafe charger and AppleCare+ warranty.',
            price: Money::fromCents(249900),
            category: 'Computers'
        );
        $manager->persist($p5);

        // 6. Sold Product example with Order and Transaction
        $p6 = new Product(
            seller: $bob,
            title: 'Vintage Seiko Automatic Chronograph (1974)',
            description: 'Collector timepiece in excellent running condition with stainless steel bracelet.',
            price: Money::fromCents(35000),
            category: 'Collectibles'
        );
        $p6->setStatus(ProductStatus::SOLD);
        $p6->setOwner($alice);
        $manager->persist($p6);

        $soldTx = new Transaction(
            sourceAccount: $aliceAccount,
            destinationAccount: $bobAccount,
            money: Money::fromCents(35000),
            type: TransactionType::PURCHASE,
            reference: 'Purchase product: Vintage Seiko Automatic Chronograph'
        );
        $soldTx->setSourceBalanceAfter($aliceAccount->getBalance());
        $soldTx->setDestinationBalanceAfter($bobAccount->getBalance());
        $manager->persist($soldTx);

        $order = new Order(
            buyer: $alice,
            seller: $bob,
            product: $p6,
            transaction: $soldTx,
            amount: Money::fromCents(35000)
        );
        $manager->persist($order);

        $manager->flush();
    }
}
