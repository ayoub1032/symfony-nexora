<?php

namespace App\Repository;

use App\Entity\Wallet;
use App\Entity\WalletGoal;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class WalletGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WalletGoal::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('g')
            ->join('g.wallet', 'w')
            ->addSelect('w')
            ->orderBy('g.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByWallet(Wallet $wallet): array
    {
        return $this->findBy(['wallet' => $wallet], ['id' => 'DESC']);
    }
}
