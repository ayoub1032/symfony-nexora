<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_gateway')]
    public function gateway(\App\Repository\WalletRepository $walletRepository): Response
    {
        return $this->render('home/gateway.html.twig', [
            'wallets' => $walletRepository->findAll(),
        ]);
    }

    #[Route('/choose-role/{role}', name: 'app_choose_role')]
    public function chooseRole(string $role, Request $request): Response
    {
        $role = strtoupper($role);
        if ($role === 'USER') {
            // Pour le User, on doit choisir un wallet (via la gateway)
            $walletId = $request->query->get('wallet_id');
            if (!$walletId) {
                return $this->redirectToRoute('app_gateway');
            }
            $request->getSession()->set('logged_in_wallet_id', $walletId);
        }

        if (!in_array($role, ['USER', 'ADMIN'])) {
            return $this->redirectToRoute('app_gateway');
        }

        $session = $request->getSession();
        $session->set('role', $role);
        $session->set('user_name', $role === 'ADMIN' ? 'Professional Admin' : 'Investor User');

        $this->addFlash('success', 'Bienvenue dans l\'espace ' . ($role === 'ADMIN' ? 'Administration' : 'Client') . ' !');

        return $this->redirectToRoute('wallet_index');
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->addFlash('info', 'Vous avez été déconnecté avec succès.');
        return $this->redirectToRoute('app_gateway');
    }
}
