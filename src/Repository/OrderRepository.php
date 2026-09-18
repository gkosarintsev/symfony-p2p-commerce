<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    /**
     * @return list<Order>
     */
    public function findForUser(User $user): array
    {
        /** @var list<Order> $result */
        $result = $this->createQueryBuilder('o')
            ->leftJoin('o.buyer', 'b')
            ->leftJoin('o.seller', 's')
            ->leftJoin('o.product', 'p')
            ->leftJoin('o.transaction', 't')
            ->addSelect('b', 's', 'p', 't')
            ->where('o.buyer = :user OR o.seller = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
