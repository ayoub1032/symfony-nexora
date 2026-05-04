<?php

namespace App\Tests\Service;

use App\Service\CurrencyService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * =============================================================================
 *  TESTS UNITAIRES — CurrencyService
 * =============================================================================
 *
 *  CurrencyService récupère le taux de change USD → TND depuis une API externe
 *  et met le résultat en cache 24h.
 *
 *  DÉFI : On ne peut pas appeler la vraie API ni le vrai cache dans un test.
 *
 *  SOLUTION : On utilise 2 stratégies de mock différentes ici.
 *
 *  STRATÉGIE 1 — Mock du cache qui exécute le callback directement :
 *    On configure $cache->get() pour qu'il appelle immédiatement la fonction
 *    callback qu'on lui passe (comme si le cache était vide).
 *    Ainsi, on peut tester la logique interne de récupération HTTP.
 *
 *  STRATÉGIE 2 — Mock du cache qui retourne une valeur directement :
 *    On simule un cache "chaud" qui retourne directement la valeur sans HTTP.
 * =============================================================================
 */
class CurrencyServiceTest extends TestCase
{
    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    /** @var CacheInterface&MockObject */
    private CacheInterface $cache;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->cache      = $this->createMock(CacheInterface::class);
    }

    /**
     * Crée le service avec les dépendances mockées.
     */
    private function createService(): CurrencyService
    {
        return new CurrencyService($this->httpClient, $this->cache);
    }

    // =========================================================================
    //  Test 1 : Taux retourné correctement depuis l'API
    // =========================================================================

    /**
     * Test : Quand l'API retourne un taux valide (ex: 3.20), ce taux doit être
     * retourné par getUsdTndRate().
     *
     * COMMENT ça marche :
     *   - On configure le cache pour qu'il appelle directement le callback.
     *   - À l'intérieur du callback, on simule une réponse HTTP avec 3.20.
     */
    public function testGetUsdTndRateReturnsApiRateOnSuccess(): void
    {
        // Arrange : fausse réponse HTTP avec le taux 3.20
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(200);
        $fakeResponse->method('toArray')->willReturn(['rates' => ['TND' => 3.20]]);

        $this->httpClient->method('request')->willReturn($fakeResponse);

        // Configurer le cache pour qu'il exécute le callback immédiatement
        // (simule un cache vide = cache miss)
        $fakeItem = $this->createMock(ItemInterface::class);
        $fakeItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($fakeItem) {
                // On appelle le callback comme le ferait un vrai cache vide
                return $callback($fakeItem);
            });

        $service = $this->createService();

        // Act
        $rate = $service->getUsdTndRate();

        // Assert
        $this->assertSame(3.20, $rate);
    }

    // =========================================================================
    //  Test 2 : Fallback sur valeur par défaut si API indisponible (non-200)
    // =========================================================================

    /**
     * Test : Si l'API retourne un code != 200, on retourne 3.15 (valeur par défaut).
     */
    public function testGetUsdTndRateReturnsFallbackOnNonOkStatus(): void
    {
        // Arrange : réponse 503 Service Unavailable
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(503);

        $this->httpClient->method('request')->willReturn($fakeResponse);

        $fakeItem = $this->createMock(ItemInterface::class);
        $fakeItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($fakeItem) {
                return $callback($fakeItem);
            });

        $service = $this->createService();

        // Act
        $rate = $service->getUsdTndRate();

        // Assert : doit retourner le fallback 3.15
        $this->assertSame(3.15, $rate);
    }

    // =========================================================================
    //  Test 3 : Fallback sur exception réseau
    // =========================================================================

    /**
     * Test : Si le client HTTP lève une exception (timeout, DNS fail…),
     * on retourne quand même 3.15 en fallback.
     */
    public function testGetUsdTndRateReturnsFallbackOnNetworkException(): void
    {
        // Arrange : exception réseau simulée
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('Network unreachable'));

        $fakeItem = $this->createMock(ItemInterface::class);
        $fakeItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($fakeItem) {
                return $callback($fakeItem);
            });

        $service = $this->createService();

        // Act
        $rate = $service->getUsdTndRate();

        // Assert
        $this->assertSame(3.15, $rate);
    }

    // =========================================================================
    //  Test 4 : Fallback si la clé 'TND' est absente de la réponse
    // =========================================================================

    /**
     * Test : Si la réponse API ne contient pas la clé 'TND' dans 'rates',
     * on utilise le fallback 3.15 (opérateur ?? dans le code).
     */
    public function testGetUsdTndRateReturnsFallbackIfRateKeyMissing(): void
    {
        // Arrange : réponse sans la clé TND
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(200);
        $fakeResponse->method('toArray')->willReturn(['rates' => ['EUR' => 0.92]]); // pas de TND

        $this->httpClient->method('request')->willReturn($fakeResponse);

        $fakeItem = $this->createMock(ItemInterface::class);
        $fakeItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($fakeItem) {
                return $callback($fakeItem);
            });

        $service = $this->createService();

        // Act
        $rate = $service->getUsdTndRate();

        // Assert
        $this->assertSame(3.15, $rate);
    }

    // =========================================================================
    //  Test 5 : Le cache retourne directement (cache chaud)
    // =========================================================================

    /**
     * Test : Si le cache est "chaud" (valeur déjà stockée), aucun appel HTTP
     * n'est fait et la valeur mise en cache est retournée.
     */
    public function testGetUsdTndRateUsesCache(): void
    {
        // Arrange : le cache retourne directement 3.25, sans appeler le callback
        $this->cache->method('get')->willReturn(3.25);

        // Le client HTTP NE DOIT PAS être appelé
        $this->httpClient->expects($this->never())->method('request');

        $service = $this->createService();

        // Act
        $rate = $service->getUsdTndRate();

        // Assert
        $this->assertSame(3.25, $rate);
    }

    // =========================================================================
    //  Test 6 : Le type de retour est toujours un float
    // =========================================================================

    /**
     * Test : getUsdTndRate() doit toujours retourner un float, peu importe
     * ce que l'API renvoie (cast explicite dans le code).
     */
    public function testGetUsdTndRateAlwaysReturnsFloat(): void
    {
        // Arrange : API retourne un entier (pas un float)
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('getStatusCode')->willReturn(200);
        $fakeResponse->method('toArray')->willReturn(['rates' => ['TND' => 3]]); // int 3, pas 3.0

        $this->httpClient->method('request')->willReturn($fakeResponse);

        $fakeItem = $this->createMock(ItemInterface::class);
        $fakeItem->method('expiresAfter')->willReturnSelf();

        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($fakeItem) {
                return $callback($fakeItem);
            });

        $service = $this->createService();
        $rate    = $service->getUsdTndRate();

        $this->assertIsFloat($rate);
    }
}
