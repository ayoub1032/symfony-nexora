<?php

namespace App\Tests\Service;

use App\Service\AiService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * =============================================================================
 *  TESTS UNITAIRES — AiService (Intégration Gemini AI)
 * =============================================================================
 *
 *  AiService dépend de :
 *    - HttpClientInterface  (réseau externe → Gemini API)
 *    - LoggerInterface      (écriture de logs)
 *    - string $geminiApiKey (valeur de configuration)
 *
 *  COMMENT TESTER SANS FAIRE DE VRAIS APPELS RÉSEAU ?
 *  → On utilise des "Mocks" (objets simulés).
 *
 *  UN MOCK :
 *    - Est un faux objet qui remplace une vraie dépendance.
 *    - On peut lui dire quoi retourner quand ses méthodes sont appelées.
 *    - Permet d'isoler la logique du service sans infrastructure réelle.
 *
 *  EXEMPLE :
 *    $httpClient = $this->createMock(HttpClientInterface::class);
 *    $httpClient->method('request')->willReturn($fakeResponse);
 *    // Maintenant, appeler $httpClient->request(...) retourne $fakeResponse
 *    // sans aller sur internet.
 * =============================================================================
 */
class AiServiceTest extends TestCase
{
    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /**
     * Avant chaque test, on crée des mocks propres.
     */
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->logger     = $this->createMock(LoggerInterface::class);
    }

    /**
     * Méthode utilitaire : crée une instance d'AiService avec une clé API donnée.
     */
    private function createService(string $apiKey = 'fake_api_key_123'): AiService
    {
        return new AiService($apiKey, $this->httpClient, $this->logger);
    }

    // =========================================================================
    //  Test 1 : Clé API non configurée
    // =========================================================================

    /**
     * Test : Si la clé API est vide ou la valeur de placeholder, on retourne
     * immédiatement un message d'erreur sans faire d'appel HTTP.
     */
    public function testGetFinancialAdviceWithEmptyApiKeyReturnsConfigMessage(): void
    {
        // Arrange : clé vide → aucun appel réseau ne doit être fait
        $service = $this->createService('');

        // Assert : httpClient->request() NE DOIT PAS être appelé
        $this->httpClient->expects($this->never())->method('request');

        // Act
        $result = $service->getFinancialAdvice(['progression' => 50]);

        // Assert : le message doit parler de la configuration de la clé
        $this->assertStringContainsString('API', $result);
    }

    /**
     * Test : La valeur placeholder "votre_cle_ici" doit aussi déclencher le message
     * de configuration (même comportement que clé vide).
     */
    public function testGetFinancialAdviceWithPlaceholderApiKeyReturnsConfigMessage(): void
    {
        $service = $this->createService('votre_cle_ici');

        $this->httpClient->expects($this->never())->method('request');

        $result = $service->getFinancialAdvice(['progression' => 50]);

        $this->assertStringContainsString('API', $result);
    }

    // =========================================================================
    //  Test 2 : Réponse API réussie (200 OK)
    // =========================================================================

    /**
     * Test : Quand l'API répond 200 avec un texte valide, ce texte est retourné.
     *
     * PATTERN : On crée un faux objet ResponseInterface qui retourne les données
     * qu'on veut simuler.
     */
    public function testGetFinancialAdviceReturnsApiTextOnSuccess(): void
    {
        // Arrange : simuler une réponse API Gemini réussie
        $fakeApiText = 'Continuez à investir, votre stratégie est solide.';
        $fakeApiData = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => $fakeApiText]
                        ]
                    ]
                ]
            ]
        ];

        // Créer un faux objet Response
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(200);
        $fakeResponse->method('toArray')->willReturn($fakeApiData);

        // Configurer httpClient pour retourner cette fausse réponse
        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('real_api_key');

        // Act
        $result = $service->getFinancialAdvice(['progression' => 60, 'goal_name' => 'Test']);

        // Assert
        $this->assertSame($fakeApiText, $result);
    }

    // =========================================================================
    //  Test 3 : Gestion du code 429 (Rate Limit)
    // =========================================================================

    /**
     * Test : Si l'API retourne 429 (trop de requêtes), on doit basculer sur
     * getMockAdvice() qui génère un conseil local.
     */
    public function testGetFinancialAdviceFallsBackToMockAdviceOn429(): void
    {
        // Arrange
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(429);

        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('real_api_key');
        $data    = ['progression' => 100, 'goal_name' => 'Mon objectif', 'days_remaining' => 5];

        // Act
        $result = $service->getFinancialAdvice($data);

        // Assert : progression = 100 → message de félicitations (mentionne l'objectif et "atteint")
        $this->assertStringContainsString('Mon objectif', $result, 'Le nom de l\'objectif doit apparaître');
        $this->assertStringContainsString('atteint', strtolower($result), 'Le mot "atteint" doit être présent');
    }

    // =========================================================================
    //  Test 4 : Erreur réseau → fallback sur getMockAdvice
    // =========================================================================

    /**
     * Test : Si le client HTTP lance une exception (ex: timeout, DNS), on bascule
     * sur le conseil local et on logue l'erreur.
     */
    public function testGetFinancialAdviceFallsBackToMockAdviceOnNetworkException(): void
    {
        // Arrange : simuler une exception réseau
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('Connection timeout'));

        // Le logger DOIT être appelé avec le niveau 'error'
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Connection timeout'));

        $service = $this->createService('real_api_key');
        $data    = ['progression' => 45, 'goal_name' => 'Épargne', 'days_remaining' => 20];

        // Act
        $result = $service->getFinancialAdvice($data);

        // Assert : le fallback doit retourner quelque chose de sensé
        $this->assertNotEmpty($result);
    }

    // =========================================================================
    //  Test 5 : Réponse API malformée
    // =========================================================================

    /**
     * Test : Si l'API répond 200 mais avec une structure JSON inattendue (sans
     * candidates), on retourne le message d'erreur générique.
     */
    public function testGetFinancialAdviceReturnsGenericErrorOnMalformedResponse(): void
    {
        // Arrange : réponse sans la clé 'candidates'
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(200);
        $fakeResponse->method('toArray')->willReturn(['error' => 'unexpected']); // pas de candidates

        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('real_api_key');

        // Act
        $result = $service->getFinancialAdvice(['progression' => 10]);

        // Assert
        $this->assertStringContainsString('impossible', strtolower($result));
    }

    // =========================================================================
    //  Test 6 : Logique de getMockAdvice (toutes les branches)
    // =========================================================================

    /**
     * Test : progression >= 100 → message de félicitations.
     * On force le fallback en utilisant une clé invalide et un statut 429.
     */
    public function testMockAdviceAt100PercentProgression(): void
    {
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(429);
        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('key');
        $result  = $service->getFinancialAdvice(['progression' => 100, 'goal_name' => 'Ferrari']);

        $this->assertStringContainsString('Ferrari', $result, 'Le nom de l\'objectif doit apparaître');
        // "atteint" est toujours présent dans le message de félicitations
        // (on évite la regex UTF-8 sur É/é qui peut varier selon la version PHP/PCRE)
        $this->assertStringContainsString('atteint', strtolower($result), 'Le mot "atteint" doit être présent');
    }

    /**
     * Test : progression >= 75 → message "vous y êtes presque".
     */
    public function testMockAdviceAt75PercentProgression(): void
    {
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(429);
        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('key');
        $result  = $service->getFinancialAdvice(['progression' => 80, 'goal_name' => 'Vacances']);

        $this->assertStringContainsString('80', $result);
    }

    /**
     * Test : deadline imminente (< 7 jours) et faible progression → message d'alerte.
     */
    public function testMockAdviceWithUrgentDeadline(): void
    {
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(429);
        $this->httpClient->method('request')->willReturn($fakeResponse);

        $service = $this->createService('key');
        $result  = $service->getFinancialAdvice([
            'progression'    => 10,
            'goal_name'      => 'Urgence',
            'days_remaining' => 3,
        ]);

        // Doit contenir le nombre de jours restants
        $this->assertStringContainsString('3', $result);
        // Doit mentionner une alerte deadline
        $this->assertStringContainsString('Alerte', $result);
    }
}
