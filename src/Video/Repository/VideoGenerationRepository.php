<?php

declare(strict_types=1);

namespace App\Video\Repository;

use App\Entity\Customer\Customer;
use App\Video\Entity\VideoGeneration;
use App\Video\Enum\VideoGenerationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\ResourceRepositoryTrait;
use Sylius\Component\Resource\Repository\RepositoryInterface;

/**
 * @extends ServiceEntityRepository<VideoGeneration>
 */
class VideoGenerationRepository extends ServiceEntityRepository implements RepositoryInterface
{
    use ResourceRepositoryTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoGeneration::class);
    }

    /**
     * @return VideoGeneration[]
     */
    public function findByCustomer(Customer $customer): array
    {
        return $this->createQueryBuilder('vg')
            ->where('vg.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('vg.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return VideoGeneration[]
     */
    public function findPendingGenerations(): array
    {
        return $this->createQueryBuilder('vg')
            ->where('vg.status = :status')
            ->setParameter('status', VideoGenerationStatus::PROCESSING->value)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return VideoGeneration[]
     */
    public function findLatestByCustomer(Customer $customer, int $limit = 10): array
    {
        return $this->createQueryBuilder('vg')
            ->where('vg.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('vg.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
