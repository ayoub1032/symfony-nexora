<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\WalletGoal;
use App\Repository\WalletGoalRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class WalletGoalController extends AbstractController
{
    #[Route('/wallet-goals', name: 'wallet_goal_index', methods: ['GET'])]
    public function index(WalletGoalRepository $goalRepository, WalletRepository $walletRepository, Request $request): Response
    {
        if (!$request->getSession()->get('role')) {
            return $this->redirectToRoute('app_login');
        }
        $role = $request->getSession()->get('role');
        $sessionWalletId = $request->getSession()->get('logged_in_wallet_id');

        if ($role === 'USER') {
            $wallet = $walletRepository->find($sessionWalletId);
            $goals = $goalRepository->findBy(['wallet' => $wallet], ['createdAt' => 'DESC']);
            $wallets = $wallet ? [$wallet] : [];
        } else {
            $goals = $goalRepository->findAllOrdered();
            $wallets = $walletRepository->findAll();
        }

        return $this->render('wallet_goal/index.html.twig', [
            'goals'   => $goals,
            'wallets' => $wallets,
        ]);
    }

    #[Route('/wallet-goals/create', name: 'wallet_goal_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $em, WalletRepository $walletRepository, ValidatorInterface $validator): Response
    {
        $sessionWalletId = $request->getSession()->get('logged_in_wallet_id');
        $role = $request->getSession()->get('role');

        $walletId    = $request->request->get('wallet_id');
        
        // Si User, on force son propre wallet
        if ($role === 'USER') {
            $walletId = $sessionWalletId;
        } elseif ($role !== 'ADMIN') {
            $this->addFlash('danger', 'Unauthorized access.');
            return $this->redirectToRoute('app_login');
        }

        $name        = $request->request->get('name');
        $name        = $request->request->get('name');
        $target      = $request->request->get('target_amount');
        $deadlineStr = $request->request->get('deadline');

        $wallet = $walletRepository->find($walletId);

        $goal = new WalletGoal();
        $goal->setWallet($wallet);
        $goal->setName((string)$name);
        $goal->setTargetAmount((string)$target);

        if ($deadlineStr) {
            $goal->setDeadline(new \DateTimeImmutable($deadlineStr));
        }

        $errors = $validator->validate($goal);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
            return $this->redirectToRoute('wallet_goal_index');
        }

        $em->persist($goal);

        // Log d'activité
        $log = new ActivityLog();
        $log->setWallet($wallet);
        $log->setMessage("Nouvel objectif créé : " . $goal->getName() . " (Cible: " . $goal->getTargetAmount() . " TND)");
        $log->setType('info');
        $em->persist($log);

        $em->flush();
        $this->addFlash('success', 'Goal "' . $goal->getName() . '" created successfully!');

        return $this->redirectToRoute('wallet_goal_index');
    }

    #[Route('/wallet-goals/update/{id}', name: 'wallet_goal_update', methods: ['POST'])]
    public function update(WalletGoal $goal, Request $request, EntityManagerInterface $em, ValidatorInterface $validator): Response
    {
        $role = $request->getSession()->get('role');
        $sessionWalletId = $request->getSession()->get('logged_in_wallet_id');

        // Sécurité : Un user ne peut modifier que ses propres buts
        if ($role === 'USER' && ($goal->getWallet()->getId() != $sessionWalletId)) {
            $this->addFlash('danger', 'You can only edit your own goals.');
            return $this->redirectToRoute('wallet_goal_index');
        } elseif ($role !== 'ADMIN' && $role !== 'USER') {
            return $this->redirectToRoute('app_login');
        }

        $name        = $request->request->get('name');
        $target      = $request->request->get('target_amount');
        $deadlineStr = $request->request->get('deadline');
        $status      = $request->request->get('status');

        $goal->setName((string)$name);
        $goal->setTargetAmount((string)$target);
        $goal->setStatus((string)$status);

        if ($deadlineStr) {
            $goal->setDeadline(new \DateTimeImmutable($deadlineStr));
        }

        $errors = $validator->validate($goal);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }
            return $this->redirectToRoute('wallet_goal_index');
        }

        $em->flush();
        $this->addFlash('success', 'Goal updated successfully!');

        return $this->redirectToRoute('wallet_goal_index');
    }

    #[Route('/wallet-goals/delete/{id}', name: 'wallet_goal_delete', methods: ['POST'])]
    public function delete(WalletGoal $goal, EntityManagerInterface $em, Request $request): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');
            return $this->redirectToRoute('wallet_goal_index');
        }
        $em->remove($goal);
        $em->flush();
        $this->addFlash('success', 'Goal deleted successfully.');

        return $this->redirectToRoute('wallet_goal_index');
    }
    #[Route('/wallet-goals/cancel/{id}', name: 'wallet_goal_cancel', methods: ['POST'])]
    public function cancel(WalletGoal $goal, EntityManagerInterface $em, Request $request): Response
    {
        $role = $request->getSession()->get('role');
        $sessionWalletId = $request->getSession()->get('logged_in_wallet_id');

        if ($role === 'USER' && ($goal->getWallet()->getId() != $sessionWalletId)) {
            $this->addFlash('danger', 'You can only cancel your own goals.');
            return $this->redirectToRoute('wallet_goal_index');
        }

        $goal->getWallet()->getId();
        $goal->setStatus('cancelled');
        
        // Log d'activité
        $log = new ActivityLog();
        $log->setWallet($goal->getWallet());
        $log->setMessage("L'objectif \"" . $goal->getName() . "\" a été ANNULÉ.");
        $log->setType('warning');
        $em->persist($log);

        $em->flush();
        $this->addFlash('warning', 'Goal "' . $goal->getName() . '" has been cancelled.');

        return $this->redirectToRoute('wallet_goal_index');
    }
}
