<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\Portfolio;
use App\Entity\UserReputation;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

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

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');

                return $this->render('security/login.html.twig', [
                    'last_email' => $email,
                ]);
            }

            if ($password === '') {
                $this->addFlash('danger', 'Password is required.');

                return $this->render('security/login.html.twig', [
                    'last_email' => $email,
                ]);
            }

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

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator
    ): Response {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        $formData = [
            'full_name' => '',
            'email' => '',
        ];

        if ($request->isMethod('POST')) {
            $fullName = trim((string) $request->request->get('full_name'));
            $email = strtolower(trim((string) $request->request->get('email')));
            $password = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            $formData = [
                'full_name' => $fullName,
                'email' => $email,
            ];

            if ($fullName === '') {
                $this->addFlash('danger', 'Full name is required.');

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            if ($userRepository->findOneByEmail($email)) {
                $this->addFlash('danger', 'An account with this email already exists.');

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            if (mb_strlen($password) < 6) {
                $this->addFlash('danger', 'Password must be at least 6 characters.');

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('danger', 'Password confirmation does not match.');

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            $user = new User();
            $user->setFullName($fullName);
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $wallet = new Wallet();
            $wallet->setOwner($fullName);
            $wallet->setBalance('0.00');
            $wallet->setCreatedAt(new \DateTime());
            $wallet->setUser($user);
            $user->setWallet($wallet);

            $portfolio = new Portfolio();
            $portfolio->setUser($user);
            $portfolio->setTotalValue(0.0);

            $reputation = new UserReputation();
            $reputation->setUser($user);
            $reputation->setCompletedContracts(0);
            $reputation->setCanceledContracts(0);
            $reputation->setTotalScore(0);
            $reputation->setRatingCount(0);

            $userErrors = $validator->validate($user);
            $walletErrors = $validator->validate($wallet);

            if (count($userErrors) > 0 || count($walletErrors) > 0) {
                foreach ($userErrors as $error) {
                    $this->addFlash('danger', $error->getMessage());
                }

                foreach ($walletErrors as $error) {
                    $this->addFlash('danger', $error->getMessage());
                }

                return $this->render('security/register.html.twig', [
                    'form_data' => $formData,
                ]);
            }

            $entityManager->persist($user);
            $entityManager->persist($wallet);
            $entityManager->persist($portfolio);
            $entityManager->persist($reputation);
            $entityManager->flush();

            $this->addFlash('success', 'Account created successfully. You can log in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'form_data' => $formData,
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
