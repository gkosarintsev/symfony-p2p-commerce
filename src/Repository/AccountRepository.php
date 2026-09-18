<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Account;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Account>
 */
class AccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Account::class);
    }

    public function findByAccountNumber(string $accountNumber): ?Account
    {
        return $this->findOneBy(['accountNumber' => $accountNumber]);
    }

    public function findByEmail(string $email): ?Account
    {
        /** @var Account|null $account */
        $account = $this->createQueryBuilder('a')
            ->join('a.user', 'u')
            ->where('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();

        return $account;
    }

    public function findWithPessimisticWriteLock(Uuid $id): ?Account
    {
        /** @var Account|null $account */
        $account = $this->getEntityManager()->find(Account::class, $id, LockMode::PESSIMISTIC_WRITE);

        return $account;
    }
}
