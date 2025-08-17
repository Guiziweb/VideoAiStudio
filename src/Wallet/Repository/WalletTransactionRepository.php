<?php

declare(strict_types=1);

namespace App\Wallet\Repository;

use App\Wallet\Entity\WalletTransaction;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends ServiceEntityRepository<WalletTransaction>
 */
class WalletTransactionRepository extends ServiceEntityRepository implements RepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletTransaction::class);
    }

    /**
     * @return WalletTransaction[]
     */
    public function findByWalletOrdered(int $walletId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.wallet = :walletId')
            ->setParameter('walletId', $walletId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createListQueryBuilder(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');
    }

    public function createByObjectIdQueryBuilder(int $objectId): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.wallet = :objectId')
            ->setParameter('objectId', $objectId)
            ->orderBy('o.createdAt', 'DESC');
    }

    /**
     * @return WalletTransaction[]
     */
    public function findTransactionsByWalletAndPeriod(?int $walletId, \DateTime $startDate, \DateTime $endDate): array
    {
        if (null === $walletId) {
            return [];
        }

        return $this->createQueryBuilder('t')
            ->andWhere('t.wallet = :walletId')
            ->andWhere('t.createdAt >= :startDate')
            ->andWhere('t.createdAt <= :endDate')
            ->setParameter('walletId', $walletId)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
