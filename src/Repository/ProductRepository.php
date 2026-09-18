<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Product;
use App\Enum\ProductStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    /**
     * @return list<Product>
     */
    public function findActiveProducts(?string $category = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.seller', 's')
            ->addSelect('s')
            ->where('p.status = :status')
            ->setParameter('status', ProductStatus::ACTIVE)
            ->orderBy('p.createdAt', 'DESC');

        if (null !== $category && '' !== $category) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category);
        }

        /** @var list<Product> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
