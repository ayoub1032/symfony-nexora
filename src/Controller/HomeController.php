<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Wallet;
use App\Repository\UserRepository;
use App\Service\FaceCompareService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
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
        ValidatorInterface $validator,
        FaceCompareService $faceCompare
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

            // Face image (optional – captured from webcam as base64)
            $faceImageBase64 = trim((string) $request->request->get('face_image', ''));

            $user = new User();
            $user->setFullName($fullName);
            $user->setEmail($email);
            $user->setRoles(['ROLE_USER']);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            if ($faceImageBase64 !== '') {
                $user->setFaceImage($faceImageBase64);
            }

            $wallet = new Wallet();
            $wallet->setOwner($fullName);
            $wallet->setBalance('0.00');
            $wallet->setUser($user);
            $user->setWallet($wallet);

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
            $entityManager->flush();

            $this->addFlash('success', 'Account created successfully. You can log in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'form_data' => $formData,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        $emailValue = trim((string) $request->query->get('email', ''));

        if ($request->isMethod('POST')) {
            $emailValue = strtolower(trim((string) $request->request->get('email')));

            if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');

                return $this->render('security/forgot_password.html.twig', [
                    'email' => $emailValue,
                ]);
            }

            $user = $userRepository->findOneByEmail($emailValue);

            if ($user) {
                $now = new \DateTimeImmutable();
                $lastRequest = $user->getResetPinRequestedAt();

                if ($lastRequest && $lastRequest > $now->modify('-1 minute')) {
                    $this->addFlash('info', 'A reset code was already requested recently. Please wait one minute and try again.');

                    return $this->redirectToRoute('app_verify_reset_pin', [
                        'email' => $emailValue,
                    ]);
                }

                $pin = $this->generateResetPin();
                $user
                    ->setResetPinCode(password_hash($pin, PASSWORD_DEFAULT))
                    ->setResetPinRequestedAt($now)
                    ->setResetPinExpiresAt($now->modify('+15 minutes'));

                $entityManager->flush();

                try {
                    $mailer->send(
                        (new Email())
                            ->from(new Address(
                                (string) $this->getParameter('app.mailer_from_address'),
                                (string) $this->getParameter('app.mailer_from_name')
                            ))
                            ->to($user->getEmail() ?? $emailValue)
                            ->subject('Nexora password reset PIN')
                            ->text($this->buildResetPinEmailText($user, $pin))
                            ->html($this->renderView('emails/reset_pin.html.twig', [
                                'user' => $user,
                                'pin' => $pin,
                                'expires_in_minutes' => 15,
                            ]))
                    );
                } catch (TransportExceptionInterface) {
                    $this->addFlash('danger', 'The reset email could not be sent. Check your Brevo sender verification and SMTP settings.');

                    return $this->render('security/forgot_password.html.twig', [
                        'email' => $emailValue,
                    ]);
                }
            }

            $this->addFlash('info', 'If that email exists in Nexora, a 6-digit reset code has been sent.');

            return $this->redirectToRoute('app_verify_reset_pin', [
                'email' => $emailValue,
            ]);
        }

        return $this->render('security/forgot_password.html.twig', [
            'email' => $emailValue,
        ]);
    }

    #[Route('/verify-reset-pin', name: 'app_verify_reset_pin', methods: ['GET', 'POST'])]
    public function verifyResetPin(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        $emailValue = strtolower(trim((string) $request->query->get('email', '')));

        if ($request->isMethod('POST')) {
            $emailValue = strtolower(trim((string) $request->request->get('email')));
            $pin = trim((string) $request->request->get('pin'));

            if ($emailValue === '' || !filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('danger', 'Please enter a valid email address.');

                return $this->render('security/verify_reset_pin.html.twig', [
                    'email' => $emailValue,
                ]);
            }

            if (!preg_match('/^\d{6}$/', $pin)) {
                $this->addFlash('danger', 'The reset PIN must contain exactly 6 digits.');

                return $this->render('security/verify_reset_pin.html.twig', [
                    'email' => $emailValue,
                ]);
            }

            $user = $userRepository->findOneByEmail($emailValue);
            if (!$user || !$user->getResetPinCode() || !$user->getResetPinExpiresAt()) {
                $this->addFlash('danger', 'Invalid or expired reset code.');

                return $this->render('security/verify_reset_pin.html.twig', [
                    'email' => $emailValue,
                ]);
            }

            if ($user->getResetPinExpiresAt() < new \DateTimeImmutable()) {
                $user->clearResetPin();
                $entityManager->flush();
                $this->addFlash('danger', 'This reset code has expired. Request a new one.');

                return $this->redirectToRoute('app_forgot_password', [
                    'email' => $emailValue,
                ]);
            }

            if (!password_verify($pin, $user->getResetPinCode())) {
                $this->addFlash('danger', 'Invalid or expired reset code.');

                return $this->render('security/verify_reset_pin.html.twig', [
                    'email' => $emailValue,
                ]);
            }

            $session = $request->getSession();
            $session->set('reset_password_user_id', $user->getId());
            $session->set('reset_password_verified_until', time() + 900);

            $this->addFlash('info', 'PIN verified. You can now choose a new password.');

            return $this->redirectToRoute('app_reset_password');
        }

        return $this->render('security/verify_reset_pin.html.twig', [
            'email' => $emailValue,
        ]);
    }

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        $session = $request->getSession();
        $resetUserId = (int) $session->get('reset_password_user_id');
        $verifiedUntil = (int) $session->get('reset_password_verified_until');

        if ($resetUserId <= 0 || $verifiedUntil < time()) {
            $session->remove('reset_password_user_id');
            $session->remove('reset_password_verified_until');
            $this->addFlash('danger', 'Your password reset session has expired. Please request a new PIN.');

            return $this->redirectToRoute('app_forgot_password');
        }

        $user = $userRepository->find($resetUserId);
        if (!$user) {
            $session->remove('reset_password_user_id');
            $session->remove('reset_password_verified_until');
            $this->addFlash('danger', 'The selected account no longer exists.');

            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = (string) $request->request->get('password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            if (mb_strlen($password) < 6) {
                $this->addFlash('danger', 'Password must be at least 6 characters.');

                return $this->render('security/reset_password.html.twig', [
                    'email' => $user->getEmail(),
                ]);
            }

            if ($password !== $confirmPassword) {
                $this->addFlash('danger', 'Password confirmation does not match.');

                return $this->render('security/reset_password.html.twig', [
                    'email' => $user->getEmail(),
                ]);
            }

            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->clearResetPin();
            $entityManager->flush();

            $session->remove('reset_password_user_id');
            $session->remove('reset_password_verified_until');

            $this->addFlash('success', 'Password updated successfully. You can log in now.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'email' => $user->getEmail(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        $this->addFlash('info', 'You have been logged out successfully.');

        return $this->redirectToRoute('app_login');
    }

    // ──────────────────────────── FACE RECOGNITION ────────────────────────────

    #[Route('/login/face', name: 'app_face_login', methods: ['GET'])]
    public function faceLoginPage(Request $request): Response
    {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        return $this->render('security/face_login.html.twig');
    }

    /**
     * JSON endpoint called by JS: receives {email, face_image (base64)}
     * Compares against stored face, returns {success, confidence, message}
     */
    #[Route('/login/face/check', name: 'app_face_login_check', methods: ['POST'])]
    public function faceLoginCheck(
        Request $request,
        UserRepository $userRepository,
        FaceCompareService $faceCompare
    ): JsonResponse {
        if ($request->getSession()->get('role')) {
            return $this->json(['success' => false, 'message' => 'Already logged in.']);
        }

        $data    = json_decode((string) $request->getContent(), true) ?? [];
        $faceB64 = trim((string) ($data['face_image'] ?? ''));

        if ($faceB64 === '') {
            return $this->json(['success' => false, 'message' => 'No face image received.'], 400);
        }

        // Scan all users who have a registered face
        $users = $userRepository->findAll();
        $bestScore = 0.0;
        $bestUser  = null;

        foreach ($users as $user) {
            if (!$user->getFaceImage()) {
                continue;
            }

            $confidence = $faceCompare->compare($user->getFaceImage(), $faceB64);

            if ($confidence !== null && $confidence > $bestScore) {
                $bestScore = $confidence;
                $bestUser  = $user;
            }
        }

        if ($bestUser === null) {
            return $this->json([
                'success' => false,
                'message' => 'No face could be detected, or no registered accounts matched. Ensure good lighting and look directly at the camera.',
            ], 422);
        }

        if ($bestScore < 80.0) {
            return $this->json([
                'success'    => false,
                'confidence' => round($bestScore, 1),
                'message'    => sprintf('Face match too low (%.1f%%). Please try again in better lighting.', $bestScore),
            ], 401);
        }

        // ✅ Match — open session
        $wallet  = $bestUser->getWallet();
        $isAdmin = $bestUser->hasRole('ROLE_ADMIN');

        $session = $request->getSession();
        $session->set('user_id',             $bestUser->getId());
        $session->set('user_name',           $bestUser->getFullName());
        $session->set('user_email',          $bestUser->getEmail());
        $session->set('role',                $isAdmin ? 'ADMIN' : 'USER');
        $session->set('logged_in_wallet_id', $wallet?->getId());

        return $this->json([
            'success'    => true,
            'confidence' => round($bestScore, 1),
            'message'    => sprintf('Identity verified (%.1f%% match). Welcome back, %s!', $bestScore, $bestUser->getFullName()),
            'redirect'   => $this->generateUrl('wallet_index'),
        ]);
    }


    private function generateResetPin(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    private function buildResetPinEmailText(User $user, string $pin): string
    {
        return sprintf(
            "Hello %s,\n\nYour Nexora password reset PIN is: %s\n\nThis code expires in 15 minutes.\nIf you did not request it, you can ignore this email.",
            $user->getFullName() ?? 'User',
            $pin
        );
    }
}
