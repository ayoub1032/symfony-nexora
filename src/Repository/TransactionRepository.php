<?php

namespace App\Repository;

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
     * Retourne la somme des transactions par catégorie pour un wallet.
     */
    public function getStatsByCategory(int $walletId): array
    {
        return $this->createQueryBuilder('t')
            ->select('COALESCE(c.name, \'Uncategorized\') as name', 'COALESCE(c.color, \'#94a3b8\') as color', 'SUM(t.amount) as total')
            ->leftJoin('t.category', 'c')
            ->where('t.wallet = :walletId')
            ->setParameter('walletId', $walletId)
            ->groupBy('c.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne l'historique de performance (Solde cumulé) sur une période.
     */
    public function getPerformanceHistory(int $walletId, string $period): array
    {
        $days = match($period) {
            '7D' => 7,
            '1M' => 30,
            default => 365,
        };

        $startDate = new \DateTimeImmutable("-" . $days . " days");

        // 1. Calculer le solde initial (somme de toutes les transactions AVANT $startDate)
        $initialBalance = $this->createQueryBuilder('t')
            ->select('SUM(CASE WHEN t.type = \'In\' THEN t.amount ELSE -t.amount END)')
            ->where('t.wallet = :walletId')
            ->andWhere('t.createdAt < :startDate')
            ->setParameter('walletId', $walletId)
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // 2. Récupérer les transactions de la période
        $qb = $this->createQueryBuilder('t')
            ->select('SUBSTRING(t.createdAt, 1, 10) as date', 't.amount', 't.type')
            ->where('t.wallet = :walletId')
            ->andWhere('t.createdAt >= :startDate')
            ->setParameter('walletId', $walletId)
            ->setParameter('startDate', $startDate)
            ->orderBy('t.createdAt', 'ASC');

        $results = $qb->getQuery()->getResult();

        $performance = [];
        $currentBalance = (float)$initialBalance;
        
        // Point de départ
        $performance[$startDate->format('Y-m-d')] = $currentBalance;

        foreach ($results as $res) {
            $amount = (float)$res['amount'];
            if ($res['type'] === 'In') {
                $currentBalance += $amount;
            } else {
                $currentBalance -= $amount;
            }
            $performance[$res['date']] = $currentBalance;
        }

        return $performance;
    }
}
