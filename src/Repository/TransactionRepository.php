<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use App\Entity\Transaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * @return list<Transaction>
     */
    public function findForAccount(Account $account, int $limit = 50): array
    {
        /** @var list<Transaction> $result */
        $result = $this->createQueryBuilder('t')
            ->leftJoin('t.sourceAccount', 'sa')
            ->leftJoin('t.destinationAccount', 'da')
            ->addSelect('sa', 'da')
            ->where('t.sourceAccount = :account OR t.destinationAccount = :account')
            ->setParameter('account', $account)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    public function findByIdempotencyKey(string $key): ?Transaction
    {
        return $this->findOneBy(['idempotencyKey' => $key]);
    }
}
