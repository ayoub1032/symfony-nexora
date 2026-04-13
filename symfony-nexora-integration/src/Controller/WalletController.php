<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Notification;
use App\Entity\Wallet;
use App\Repository\ActivityLogRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WalletController extends AbstractController
{
    #[Route('/wallets', name: 'wallet_index', methods: ['GET'])]
    public function index(WalletRepository $walletRepository, ActivityLogRepository $logRepository, \App\Repository\WalletGoalRepository $goalRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->getSession()->get('role') === 'USER') {
            $walletId = $request->getSession()->get('logged_in_wallet_id');
            $wallet = $walletRepository->find($walletId);
            $wallets = $wallet ? [$wallet] : [];
            $totalBalance = $wallet ? (float)$wallet->getBalance() : 0;
            $recentLogs = []; // Cacher les logs pour le User
            $goals = $wallet ? $goalRepository->findBy(['wallet' => $wallet], ['createdAt' => 'DESC']) : [];
        } else {
            $wallets = $walletRepository->findAllOrdered();
            $totalBalance = array_reduce($wallets, fn($carry, $item) => $carry + (float)$item->getBalance(), 0);
            $recentLogs = $logRepository->findRecent(10);
            $goals = $goalRepository->findAll();
        }

        return $this->render('wallet/index.html.twig', [
            'wallets'      => $wallets,
            'totalBalance' => $totalBalance,
            'activeCount'  => count($wallets),
            'recentLogs'   => $recentLogs,
            'goals'        => $goals,
        ]);
    }

    #[Route('/wallets/create', name: 'wallet_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('wallet_index');
        }
        $wallet = new Wallet();
        $wallet->setOwner((string)$request->request->get('owner'));
        $wallet->setBalance((string)$request->request->get('balance', 0));

        $errors = $validator->validate($wallet);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        } else {
            $entityManager->persist($wallet);

            // Log d'activité
            $log = new ActivityLog();
            $log->setWallet($wallet);
            $log->setMessage("Nouveau portefeuille créé pour " . $wallet->getOwner() . " (TND)");
            $log->setType('success');
            $entityManager->persist($log);

            $entityManager->flush();
            $this->addFlash('success', 'Wallet created successfully!');
        }

        return $this->redirectToRoute('wallet_index');
    }

    #[Route('/wallets/update/{id}', name: 'wallet_update', methods: ['POST'])]
    public function update(Wallet $wallet, Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('wallet_index');
        }
        $wallet->setOwner((string)$request->request->get('owner'));

        $errors = $validator->validate($wallet);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        } else {
            $entityManager->flush();
            $this->addFlash('success', 'Wallet updated successfully!');
        }

        return $this->redirectToRoute('wallet_index');
    }

    #[Route('/wallets/delete/{id}', name: 'wallet_delete', methods: ['POST'])]
    public function delete(Wallet $wallet, EntityManagerInterface $entityManager, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('wallet_index');
        }
        /**
         * Règle Métier 2 : Protection contre la suppression de portefeuilles non vides
         */
        if ((float)$wallet->getBalance() > 1.00) {
            $this->addFlash('danger', sprintf(
                'Security Alert: Cannot delete wallet "%s" because it still contains %.2f TND. Please empty the wallet first.',
                $wallet->getOwner(),
                (float)$wallet->getBalance()
            ));
            return $this->redirectToRoute('wallet_index');
        }

        $entityManager->remove($wallet);
        $entityManager->flush();
        $this->addFlash('success', 'Wallet deleted successfully.');

        return $this->redirectToRoute('wallet_index');
    }

    #[Route('/wallets/transaction/{id}', name: 'wallet_transaction', methods: ['POST'])]
    public function transaction(Wallet $wallet, Request $request, EntityManagerInterface $entityManager): Response
    {
        $type   = $request->request->get('type');
        $amount = (float)$request->request->get('amount');

        if ($amount <= 0) {
            $this->addFlash('danger', 'Amount must be greater than zero.');
            return $this->redirectToRoute('wallet_index');
        }

        $currentBalance = (float)$wallet->getBalance();

        // Récupérer les ID des buts déjà atteints avant la transaction
        $alreadyAchievedIds = $wallet->getWalletGoals()
            ->filter(fn($g) => $g->getProgression() >= 100)
            ->map(fn($g) => $g->getId())
            ->toArray();

        if ($type === 'Withdrawal' && $currentBalance < $amount) {
            $this->addFlash('danger', 'Insufficient balance for this withdrawal.');
            return $this->redirectToRoute('wallet_index');
        }

        if ($type === 'Deposit') {
            $wallet->setBalance((string)($currentBalance + $amount));
            $message = sprintf('Deposit of %.2f TND added to %s\'s wallet.', $amount, $wallet->getOwner());
            $logType = 'success';
        } else {
            $wallet->setBalance((string)($currentBalance - $amount));
            $message = sprintf('Withdrawal of %.2f TND made from %s\'s wallet.', $amount, $wallet->getOwner());
            $logType = 'warning';
        }

        // Log d'activité
        $log = new ActivityLog();
        $log->setWallet($wallet);
        $log->setMessage($message);
        $log->setType($logType);
        $entityManager->persist($log);

        $this->addFlash('success', $message);

        // Système de Notifications
        $notification = new Notification();
        $notification->setWallet($wallet);
        $notification->setMessage($message);
        $notification->setType($logType);
        $entityManager->persist($notification);

        // Alerte Solde Bas
        if ((float)$wallet->getBalance() < 50) {
            $this->addFlash('warning', sprintf('⚠️ ALERTE : Le solde de %s est descendu sous le seuil critique (%.2f TND) !', $wallet->getOwner(), (float)$wallet->getBalance()));
        }

        // Vérifier si de nouveaux buts sont atteints
        if ($type === 'Deposit') {
            foreach ($wallet->getWalletGoals() as $goal) {
                if ($goal->getProgression() >= 100 && !in_array($goal->getId(), $alreadyAchievedIds)) {
                    $this->addFlash('congrats', sprintf('🏆 TOUTES NOS FÉLICITATIONS ! L\'objectif "%s" est désormais ATTEINT !', $goal->getName()));
                }
            }
        }

        $entityManager->flush();

        return $this->redirectToRoute('wallet_index');
    }
}
