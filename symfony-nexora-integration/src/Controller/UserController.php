<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\UserRepository;
use App\Repository\WalletRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    #[Route('/users', name: 'user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository, WalletRepository $walletRepository): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');

            return $this->redirectToRoute('wallet_index');
        }

        return $this->render('user/index.html.twig', [
            'users' => $userRepository->findBy([], ['id' => 'DESC']),
            'wallets' => $walletRepository->findBy([], ['id' => 'ASC']),
        ]);
    }

    #[Route('/users/create', name: 'user_create', methods: ['POST'])]
    public function create(
        Request $request,
        UserRepository $userRepository,
        WalletRepository $walletRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');

            return $this->redirectToRoute('wallet_index');
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $fullName = trim((string) $request->request->get('full_name'));
        $password = (string) $request->request->get('password');
        $role = (string) $request->request->get('role', 'ROLE_USER');
        $walletId = $request->request->get('wallet_id');

        if ($userRepository->findOneByEmail($email)) {
            $this->addFlash('danger', 'A user with this email already exists.');

            return $this->redirectToRoute('user_index');
        }

        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles([$role === 'ROLE_ADMIN' ? 'ROLE_ADMIN' : 'ROLE_USER']);

        if ($password === '') {
            $this->addFlash('danger', 'Password is required.');

            return $this->redirectToRoute('user_index');
        }

        if (mb_strlen($password) < 6) {
            $this->addFlash('danger', 'Password must be at least 6 characters.');

            return $this->redirectToRoute('user_index');
        }

        $user->setPassword($passwordHasher->hashPassword($user, $password));

        $wallet = $this->resolveWallet($walletRepository, $walletId, $user);
        if (!$wallet && $walletId) {
            $this->addFlash('danger', 'Selected wallet is already linked to another user.');

            return $this->redirectToRoute('user_index');
        }

        if ($wallet) {
            $wallet->setUser($user);
            $wallet->setOwner($user->getFullName() ?? $wallet->getOwner());
            $user->setWallet($wallet);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('user_index');
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'User created successfully.');

        return $this->redirectToRoute('user_index');
    }

    #[Route('/users/update/{id}', name: 'user_update', methods: ['POST'])]
    public function update(
        User $user,
        Request $request,
        UserRepository $userRepository,
        WalletRepository $walletRepository,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');

            return $this->redirectToRoute('wallet_index');
        }

        $email = strtolower(trim((string) $request->request->get('email')));
        $fullName = trim((string) $request->request->get('full_name'));
        $password = (string) $request->request->get('password');
        $role = (string) $request->request->get('role', 'ROLE_USER');
        $walletId = $request->request->get('wallet_id');

        $existingUser = $userRepository->findOneByEmail($email);
        if ($existingUser && $existingUser->getId() !== $user->getId()) {
            $this->addFlash('danger', 'A user with this email already exists.');

            return $this->redirectToRoute('user_index');
        }

        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRoles([$role === 'ROLE_ADMIN' ? 'ROLE_ADMIN' : 'ROLE_USER']);

        if ($password !== '') {
            if (mb_strlen($password) < 6) {
                $this->addFlash('danger', 'Password must be at least 6 characters.');

                return $this->redirectToRoute('user_index');
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
        }

        $currentWallet = $user->getWallet();
        $wallet = $this->resolveWallet($walletRepository, $walletId, $user);
        if (!$wallet && $walletId) {
            $this->addFlash('danger', 'Selected wallet is already linked to another user.');

            return $this->redirectToRoute('user_index');
        }

        if ($currentWallet && (!$wallet || $currentWallet->getId() !== $wallet->getId())) {
            $currentWallet->setUser(null);
        }

        if ($wallet) {
            $wallet->setUser($user);
            $wallet->setOwner($user->getFullName() ?? $wallet->getOwner());
            $user->setWallet($wallet);
        } else {
            $user->setWallet(null);
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('danger', $error->getMessage());
            }

            return $this->redirectToRoute('user_index');
        }

        $entityManager->flush();

        if ((int) $request->getSession()->get('user_id') === $user->getId()) {
            $request->getSession()->set('user_name', $user->getFullName());
            $request->getSession()->set('user_email', $user->getEmail());
            $request->getSession()->set('role', $user->hasRole('ROLE_ADMIN') ? 'ADMIN' : 'USER');
            $request->getSession()->set('logged_in_wallet_id', $wallet?->getId());
        }

        $this->addFlash('success', 'User updated successfully.');

        return $this->redirectToRoute('user_index');
    }

    #[Route('/users/delete/{id}', name: 'user_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->getSession()->get('role') !== 'ADMIN') {
            $this->addFlash('danger', 'Reserved for Admin access.');

            return $this->redirectToRoute('wallet_index');
        }

        if ((int) $request->getSession()->get('user_id') === $user->getId()) {
            $this->addFlash('danger', 'You cannot delete your own account while logged in.');

            return $this->redirectToRoute('user_index');
        }

        if ($user->getWallet()) {
            $user->getWallet()->setUser(null);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', 'User deleted successfully.');

        return $this->redirectToRoute('user_index');
    }

    private function resolveWallet(WalletRepository $walletRepository, mixed $walletId, User $user): ?Wallet
    {
        if (!$walletId) {
            return null;
        }

        $wallet = $walletRepository->find((int) $walletId);
        if (!$wallet) {
            return null;
        }

        $linkedUser = $wallet->getUser();
        if ($linkedUser && $linkedUser->getId() !== $user->getId()) {
            return null;
        }

        return $wallet;
    }
}
