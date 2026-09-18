<?php

declare(strict_types=1);

namespace App\Domain\Product\CommandHandler;

use App\Domain\Account\Exception\AccountNotFoundException;
use App\Domain\Account\ValueObject\Money;
use App\Domain\Product\Command\CreateProductCommand;
use App\Entity\Product;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateProductHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(CreateProductCommand $command): Product
    {
        $seller = $this->userRepository->findOneBy(['email' => $command->sellerEmail]);
        if (null === $seller) {
            throw new AccountNotFoundException($command->sellerEmail);
        }

        $money = Money::fromCents($command->priceCents);

        $product = new Product(
            seller: $seller,
            title: $command->title,
            description: $command->description,
            price: $money,
            category: $command->category
        );

        $this->em->persist($product);
        $this->em->flush();

        return $product;
    }
}
