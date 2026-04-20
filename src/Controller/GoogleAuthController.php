<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GoogleAuthController extends AbstractController
{
    private const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USER_URL  = 'https://www.googleapis.com/oauth2/v3/userinfo';
    private const SCOPES    = 'openid email profile';

    public function __construct(
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly HttpClientInterface $httpClient,
    ) {}

    // ── Build the Google OAuth redirect URL ──────────────────────────────────

    private function buildAuthUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'             => $this->clientId,
            'redirect_uri'          => $this->redirectUri,
            'response_type'         => 'code',
            'scope'                 => self::SCOPES,
            'access_type'           => 'online',
            'state'                 => $state,
            'prompt'                => 'select_account',
        ]);
    }

    // ── Exchange code → access token → user profile ──────────────────────────

    private function fetchGoogleProfile(string $code): ?array
    {
        try {
            $tokenRes = $this->httpClient->request('POST', self::TOKEN_URL, [
                'body' => [
                    'code'          => $code,
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'redirect_uri'  => $this->redirectUri,
                    'grant_type'    => 'authorization_code',
                ],
            ]);

            $token = $tokenRes->toArray(false);
            if (empty($token['access_token'])) {
                return null;
            }

            $profileRes = $this->httpClient->request('GET', self::USER_URL, [
                'headers' => ['Authorization' => 'Bearer ' . $token['access_token']],
            ]);

            return $profileRes->toArray(false);
        } catch (\Throwable) {
            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════
    //  LOGIN WITH GOOGLE
    // ═══════════════════════════════════════════════════════════════

    #[Route('/auth/google/login', name: 'google_login_start')]
    public function loginStart(Request $request): RedirectResponse
    {
        if ($request->getSession()->get('role')) {
            return $this->redirectToRoute('wallet_index');
        }

        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('google_oauth_state', $state);
        $request->getSession()->set('google_oauth_intent', 'login');

        return new RedirectResponse($this->buildAuthUrl($state));
    }

    // ═══════════════════════════════════════════════════════════════
    //  LINK GOOGLE TO REGISTRATION (called from register page)
    // ═══════════════════════════════════════════════════════════════

    #[Route('/auth/google/link', name: 'google_link_start')]
    public function linkStart(Request $request): RedirectResponse
    {
        $state = bin2hex(random_bytes(16));
        $request->getSession()->set('google_oauth_state', $state);
        $request->getSession()->set('google_oauth_intent', 'link');

        return new RedirectResponse($this->buildAuthUrl($state));
    }

    // ═══════════════════════════════════════════════════════════════
    //  SHARED CALLBACK
    // ═══════════════════════════════════════════════════════════════

    #[Route('/auth/google/callback', name: 'google_callback')]
    public function callback(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ): Response {
        $session = $request->getSession();

        // CSRF state check
        $returnedState = $request->query->get('state', '');
        $savedState    = (string) $session->get('google_oauth_state', '');

        if ($returnedState !== $savedState || $savedState === '') {
            $this->addFlash('danger', 'Invalid OAuth state. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $code = $request->query->get('code', '');
        if ($code === '') {
            $this->addFlash('danger', 'Google authentication was cancelled or failed.');
            return $this->redirectToRoute('app_login');
        }

        $profile = $this->fetchGoogleProfile($code);

        if (!$profile || empty($profile['sub'])) {
            $this->addFlash('danger', 'Could not retrieve your Google profile. Please try again.');
            return $this->redirectToRoute('app_login');
        }

        $intent   = (string) $session->get('google_oauth_intent', 'login');
        $googleId = (string) $profile['sub'];
        $googleEmail = strtolower((string) ($profile['email'] ?? ''));
        $googleName  = (string) ($profile['name'] ?? '');

        // Clean up session keys
        $session->remove('google_oauth_state');
        $session->remove('google_oauth_intent');

        // ── INTENT: link (called from registration page) ──────────────────
        if ($intent === 'link') {
            // Store google profile in session so the register form can use it
            $session->set('google_link_id',    $googleId);
            $session->set('google_link_email', $googleEmail);
            $session->set('google_link_name',  $googleName);

            $this->addFlash('success', sprintf(
                'Google account "%s" linked successfully. Complete your registration below.',
                $googleEmail
            ));

            return $this->redirectToRoute('app_register');
        }

        // ── INTENT: login ─────────────────────────────────────────────────
        // 1. Try to find by google_id
        $user = $userRepository->findOneByGoogleId($googleId);

        // 2. If not found by google_id, try email match (auto-link convenience)
        if (!$user && $googleEmail !== '') {
            $user = $userRepository->findOneByEmail($googleEmail);
            if ($user) {
                // Silently link the google_id now
                $user->setGoogleId($googleId);
                $entityManager->flush();
            }
        }

        if (!$user) {
            $this->addFlash('danger', 'No Nexora account is linked to this Google account. Please register first, then link your Google account during registration.');
            return $this->redirectToRoute('app_login');
        }

        // ✅ Log in
        $wallet  = $user->getWallet();
        $isAdmin = $user->hasRole('ROLE_ADMIN');

        $session->set('user_id',             $user->getId());
        $session->set('user_name',           $user->getFullName());
        $session->set('user_email',          $user->getEmail());
        $session->set('role',                $isAdmin ? 'ADMIN' : 'USER');
        $session->set('logged_in_wallet_id', $wallet?->getId());

        $this->addFlash('success', sprintf('Welcome back, %s! Signed in with Google.', $user->getFullName()));

        return $this->redirectToRoute('wallet_index');
    }
}
