<?php

namespace App\Repository;

use App\Entity\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findUnreadByWallet($wallet): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.wallet = :wallet')
            ->andWhere('n.isRead = false')
            ->setParameter('wallet', $wallet)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Notification[]
     */
    public function findLatestByWallet($wallet, int $limit = 5): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.wallet = :wallet')
            ->setParameter('wallet', $wallet)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
