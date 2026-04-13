<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Transaction;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:seed-transactions',
    description: 'Crée des données de test pour les transactions et catégories.',
)]
class SeedTransactionsCommand extends Command
{
    private EntityManagerInterface $entityManager;
    private WalletRepository $walletRepository;

    public function __construct(EntityManagerInterface $entityManager, WalletRepository $walletRepository)
    {
        parent::__construct();
        $this->entityManager = $entityManager;
        $this->walletRepository = $walletRepository;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wallets = $this->walletRepository->findAll();

        if (empty($wallets)) {
            $io->error('Aucun wallet trouvé. Veuillez d\'abord créer des portefeuilles.');
            return Command::FAILURE;
        }

        // 1. Créer des catégories
        $categoriesData = [
            ['Trading Profit', 'fa-chart-line', '#10b981'],
            ['Withdrawal', 'fa-money-bill-transfer', '#ef4444'],
            ['Crypto Buy', 'fa-bitcoin', '#f59e0b'],
            ['Fees', 'fa-percentage', '#6b7280'],
            ['Deposit', 'fa-plus-circle', '#6366f1'],
        ];

        $categories = [];
        foreach ($categoriesData as $data) {
            $category = new Category();
            $category->setName($data[0]);
            $category->setIcon('fas ' . $data[1]);
            $category->setColor($data[2]);
            $this->entityManager->persist($category);
            $categories[] = $category;
        }

        // 2. Créer des transactions pour chaque wallet au cours des 30 derniers jours
        foreach ($wallets as $wallet) {
            $io->info('Génération de transactions pour: ' . $wallet->getOwner());
            
            for ($i = 30; $i >= 0; $i--) {
                // On crée 0 à 2 transactions par jour
                $numTrans = rand(0, 2);
                for ($j = 0; $j < $numTrans; $j++) {
                    $transaction = new Transaction();
                    $transaction->setWallet($wallet);
                    
                    $category = $categories[array_rand($categories)];
                    $transaction->setCategory($category);
                    
                    $amount = rand(50, 500);
                    $transaction->setAmount((string)$amount);
                    
                    // Aléatoire In/Out
                    $type = (rand(0, 10) > 4) ? 'In' : 'Out'; 
                    $transaction->setType($type);
                    
                    $date = new \DateTimeImmutable("-" . $i . " days " . rand(0, 23) . " hours");
                    $transaction->setCreatedAt($date);
                    
                    $this->entityManager->persist($transaction);
                }
            }
        }

        $this->entityManager->flush();
        $io->success('Seeding terminé avec succès !');

        return Command::SUCCESS;
    }
}
