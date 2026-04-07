<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(Request $request): Response
    {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        return $this->redirectToRoute('app_login');
    }

    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, UserRepository $userRepository): Response
    {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email'));
            $password = (string) $request->request->get('password');

            $user = $userRepository->findOneByEmail($email);

            if (!$user || !password_verify($password, (string) $user->getPassword())) {
                $this->addFlash('danger', 'Invalid email or password.');

                return $this->render('security/login.html.twig', [
                    'last_email' => $email,
                ]);
            }

            $isAdmin = $user->hasRole('ROLE_ADMIN');
            $wallet = $user->getWallet();

            if (!$isAdmin && !$wallet) {
                $this->addFlash('danger', 'This user account is not linked to a wallet yet.');

                return $this->render('security/login.html.twig', [
                    'last_email' => $email,
                ]);
            }

            $session = $request->getSession();
            $session->set('user_id', $user->getId());
            $session->set('user_name', $user->getFullName());
            $session->set('user_email', $user->getEmail());
            $session->set('role', $isAdmin ? 'ADMIN' : 'USER');
            $session->set('logged_in_wallet_id', $wallet?->getId());

            $this->addFlash('success', 'Login successful.');

            return $this->redirectToRoute('wallet_index');
        }

        return $this->render('security/login.html.twig', [
            'last_email' => '',
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->addFlash('info', 'You have been logged out successfully.');

        return $this->redirectToRoute('app_login');
    }
}
