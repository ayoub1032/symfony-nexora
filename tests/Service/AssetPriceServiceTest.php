<?php

namespace App\Tests\Service;

use App\Entity\Asset;
use App\Entity\Portfolio;
use App\Entity\PortfolioAsset;
use App\Repository\AssetRepository;
use App\Service\AssetPriceService;
use App\Service\CurrencyService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * =============================================================================
 *  TESTS UNITAIRES — AssetPriceService
 * =============================================================================
 *
 *  AssetPriceService est le service le PLUS COMPLEXE à tester car il a
 *  5 dépendances :
 *    1. HttpClientInterface  → appels CoinGecko API
 *    2. EntityManagerInterface → flush() en BDD
 *    3. AssetRepository      → findAll() des actifs
 *    4. LoggerInterface      → journalisation des erreurs
 *    5. CurrencyService      → taux USD→TND
 *
 *  STRATÉGIE : On mock toutes ces dépendances pour tester la logique
 *  du service de façon COMPLÈTEMENT ISOLÉE.
 *
 *  MÉTHODES TESTÉES :
 *    - syncAssetPrices() : met à jour les prix depuis CoinGecko
 *    - recalculatePortfolios() : recalcule les valeurs totales
 *
 *  COMMENT LIRE CES TESTS :
 *    Chaque test configure les mocks pour simuler un scénario précis,
 *    appelle la méthode testée, puis vérifie le comportement résultant.
 * =============================================================================
 */
class AssetPriceServiceTest extends TestCase
{
    /** @var HttpClientInterface&MockObject */
    private HttpClientInterface $httpClient;

    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var AssetRepository&MockObject */
    private AssetRepository $assetRepository;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var CurrencyService&MockObject */
    private CurrencyService $currencyService;

    protected function setUp(): void
    {
        $this->httpClient      = $this->createMock(HttpClientInterface::class);
        $this->em              = $this->createMock(EntityManagerInterface::class);
        $this->assetRepository = $this->createMock(AssetRepository::class);
        $this->logger          = $this->createMock(LoggerInterface::class);
        $this->currencyService = $this->createMock(CurrencyService::class);
    }

    /**
     * Crée le service avec toutes les dépendances mockées.
     */
    private function createService(): AssetPriceService
    {
        return new AssetPriceService(
            $this->httpClient,
            $this->em,
            $this->assetRepository,
            $this->logger,
            $this->currencyService
        );
    }

    /**
     * Crée un Asset entity avec les valeurs données.
     */
    private function makeAsset(string $name, string $symbol, float $value = 1.0): Asset
    {
        $asset = new Asset();
        $asset->setName($name)->setSymbol($symbol)->setValue($value)->setType('Crypto');
        return $asset;
    }

    // =========================================================================
    //  Test 1 : syncAssetPrices() — Portfolio vide
    // =========================================================================

    /**
     * Test : Si aucun actif en base, la méthode retourne 0 sans faire d'appel HTTP.
     */
    public function testSyncAssetPricesReturnsZeroWithNoAssets(): void
    {
        // Arrange : repository retourne un tableau vide
        $this->assetRepository->method('findAll')->willReturn([]);

        // Aucun appel HTTP ne doit être fait
        $this->httpClient->expects($this->never())->method('request');

        $service = $this->createService();

        // Act
        $count = $service->syncAssetPrices();

        // Assert
        $this->assertSame(0, $count);
    }

    // =========================================================================
    //  Test 2 : syncAssetPrices() — Actifs sans mapping CoinGecko
    // =========================================================================

    /**
     * Test : Si tous les actifs ont des symboles inconnus (ex: 'UNKNOWN'),
     * aucun appel HTTP n'est fait et on retourne 0.
     */
    public function testSyncAssetPricesReturnsZeroWithUnknownSymbols(): void
    {
        // Arrange : actif avec un symbole non mapé vers CoinGecko
        $unknownAsset = $this->makeAsset('UnknownCoin', 'UNKN', 1.0);
        $this->assetRepository->method('findAll')->willReturn([$unknownAsset]);

        $this->httpClient->expects($this->never())->method('request');

        $service = $this->createService();
        $count   = $service->syncAssetPrices();

        $this->assertSame(0, $count);
    }

    // =========================================================================
    //  Test 3 : syncAssetPrices() — Mise à jour réussie
    // =========================================================================

    /**
     * Test : syncAssetPrices() met à jour correctement les actifs BTC et ETH.
     *
     *  SCÉNARIO :
     *    - CoinGecko retourne BTC = $95 000 USD et ETH = $3 500 USD
     *    - Le taux USD/TND = 3.20
     *    - BTC doit être mis à jour : 95 000 × 3.20 = 304 000 TND
     *    - ETH doit être mis à jour : 3 500 × 3.20 = 11 200 TND
     *    - La méthode retourne 2 (nombre d'actifs mis à jour)
     */
    public function testSyncAssetPricesUpdatesAssetsCorrectly(): void
    {
        // Arrange : 2 actifs connus en base
        $btc = $this->makeAsset('Bitcoin',  'BTC', 50_000.0);
        $eth = $this->makeAsset('Ethereum', 'ETH',  3_000.0);

        $this->assetRepository->method('findAll')->willReturn([$btc, $eth]);

        // Taux de change : 1 USD = 3.20 TND
        $this->currencyService->method('getUsdTndRate')->willReturn(3.20);

        // Réponse CoinGecko simulée
        $fakeApiData = [
            'bitcoin'  => ['usd' => 95_000.0],
            'ethereum' => ['usd' =>  3_500.0],
        ];
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('toArray')->willReturn($fakeApiData);

        $this->httpClient->method('request')->willReturn($fakeResponse);

        // flush() doit être appelé UNE FOIS
        $this->em->expects($this->once())->method('flush');

        $service = $this->createService();

        // Act
        $count = $service->syncAssetPrices();

        // Assert : 2 actifs mis à jour
        $this->assertSame(2, $count);

        // Vérifier les nouvelles valeurs : prix USD × taux TND
        $this->assertEqualsWithDelta(95_000.0 * 3.20, $btc->getValue(), 0.01, 'Prix BTC incorrect');
        $this->assertEqualsWithDelta( 3_500.0 * 3.20, $eth->getValue(), 0.01, 'Prix ETH incorrect');
    }

    // =========================================================================
    //  Test 4 : syncAssetPrices() — Actif inconnu de l'API mais connu localement
    // =========================================================================

    /**
     * Test : Si CoinGecko ne retourne pas de données pour un actif connu
     * (ex: BTC absent de la réponse), cet actif n'est PAS mis à jour.
     * Seuls les actifs présents dans la réponse API sont traités.
     */
    public function testSyncAssetPricesSkipsAssetsNotInApiResponse(): void
    {
        $btc  = $this->makeAsset('Bitcoin', 'BTC', 50_000.0);
        $link = $this->makeAsset('Chainlink', 'LINK', 20.0);

        $this->assetRepository->method('findAll')->willReturn([$btc, $link]);
        $this->currencyService->method('getUsdTndRate')->willReturn(3.10);

        // L'API ne retourne que BTC, pas chainlink
        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('toArray')->willReturn([
            'bitcoin' => ['usd' => 100_000.0],
            // 'chainlink' absent
        ]);
        $this->httpClient->method('request')->willReturn($fakeResponse);
        $this->em->method('flush');

        $service = $this->createService();
        $count   = $service->syncAssetPrices();

        // Seul BTC a été mis à jour
        $this->assertSame(1, $count);

        // LINK ne doit PAS avoir été modifié (reste à 20.0)
        $this->assertSame(20.0, $link->getValue(), 'LINK ne doit pas être modifié');

        // BTC doit avoir été mis à jour
        $this->assertEqualsWithDelta(100_000.0 * 3.10, $btc->getValue(), 0.01);
    }

    // =========================================================================
    //  Test 5 : syncAssetPrices() — Erreur réseau
    // =========================================================================

    /**
     * Test : Si le client HTTP lance une exception, la méthode retourne 0
     * et logue l'erreur. Aucun flush() ne doit être appelé.
     */
    public function testSyncAssetPricesReturnsZeroOnNetworkException(): void
    {
        $btc = $this->makeAsset('Bitcoin', 'BTC', 50_000.0);
        $this->assetRepository->method('findAll')->willReturn([$btc]);
        $this->currencyService->method('getUsdTndRate')->willReturn(3.10);

        // Simuler une erreur réseau
        $this->httpClient->method('request')
            ->willThrowException(new \RuntimeException('DNS resolution failed'));

        // Le logger doit recevoir un appel error()
        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('DNS resolution failed'));

        // flush() NE DOIT PAS être appelé en cas d'erreur
        $this->em->expects($this->never())->method('flush');

        $service = $this->createService();
        $count   = $service->syncAssetPrices();

        $this->assertSame(0, $count);
        // Le prix de BTC ne doit pas avoir changé
        $this->assertSame(50_000.0, $btc->getValue());
    }

    // =========================================================================
    //  Test 6 : recalculatePortfolios()
    // =========================================================================

    /**
     * Test : recalculatePortfolios() recalcule correctement la valeur totale
     * de chaque portfolio et appelle flush().
     *
     *  SCÉNARIO :
     *    - Portfolio avec 1 BTC à 60 000 TND (prix actuel)
     *    - Après recalcul, totalValue = 1 × 60 000 = 60 000 TND
     */
    public function testRecalculatePortfoliosUpdatesTotalValue(): void
    {
        // Arrange : créer un portfolio avec 1 BTC
        $asset = $this->makeAsset('Bitcoin', 'BTC', 60_000.0);

        $pa = new PortfolioAsset();
        $pa->setAsset($asset)->setQuantity(1)->setAvgPrice(50_000.0);

        $portfolio = new Portfolio();
        $portfolio->addPortfolioAsset($pa);

        // Le repository générique de Portfolio retourne notre portfolio
        $portfolioRepo = $this->createMock(EntityRepository::class);
        $portfolioRepo->method('findAll')->willReturn([$portfolio]);

        $this->em->method('getRepository')
            ->with(Portfolio::class)
            ->willReturn($portfolioRepo);

        // flush() doit être appelé
        $this->em->expects($this->once())->method('flush');

        $service = $this->createService();

        // Act
        $service->recalculatePortfolios();

        // Assert : totalValue = 1 × 60 000 = 60 000
        $this->assertSame(60_000.0, $portfolio->getTotalValue());
    }

    /**
     * Test : recalculatePortfolios() avec plusieurs portfolios.
     *
     *  Portfolio A : 2 ETH × 3 000 = 6 000 TND
     *  Portfolio B : 1 BTC × 60 000 = 60 000 TND
     */
    public function testRecalculatePortfoliosWithMultiplePortfolios(): void
    {
        // Portfolio A : 2 ETH
        $eth  = $this->makeAsset('Ethereum', 'ETH', 3_000.0);
        $paA  = new PortfolioAsset();
        $paA->setAsset($eth)->setQuantity(2)->setAvgPrice(2_500.0);
        $portfolioA = new Portfolio();
        $portfolioA->addPortfolioAsset($paA);

        // Portfolio B : 1 BTC
        $btc  = $this->makeAsset('Bitcoin', 'BTC', 60_000.0);
        $paB  = new PortfolioAsset();
        $paB->setAsset($btc)->setQuantity(1)->setAvgPrice(55_000.0);
        $portfolioB = new Portfolio();
        $portfolioB->addPortfolioAsset($paB);

        $portfolioRepo = $this->createMock(EntityRepository::class);
        $portfolioRepo->method('findAll')->willReturn([$portfolioA, $portfolioB]);

        $this->em->method('getRepository')->willReturn($portfolioRepo);
        $this->em->expects($this->once())->method('flush');

        $service = $this->createService();
        $service->recalculatePortfolios();

        $this->assertSame(6_000.0,  $portfolioA->getTotalValue(), 'Portfolio ETH incorrect');
        $this->assertSame(60_000.0, $portfolioB->getTotalValue(), 'Portfolio BTC incorrect');
    }

    /**
     * Test : recalculatePortfolios() avec aucun portfolio → flush quand même.
     */
    public function testRecalculatePortfoliosWithNoPortfolios(): void
    {
        $portfolioRepo = $this->createMock(EntityRepository::class);
        $portfolioRepo->method('findAll')->willReturn([]);

        $this->em->method('getRepository')->willReturn($portfolioRepo);
        $this->em->expects($this->once())->method('flush');

        $service = $this->createService();

        // Ne doit pas lancer d'exception
        $service->recalculatePortfolios();

        // Assertion triviale pour confirmer l'exécution sans erreur
        $this->assertTrue(true);
    }

    // =========================================================================
    //  Test 7 : Symboles insensibles à la casse
    // =========================================================================

    /**
     * Test : Le service normalise les symboles en majuscules avant de les mapper.
     * Un asset avec symbole 'btc' (minuscule) doit quand même être mis à jour.
     */
    public function testSyncAssetPricesIsCaseInsensitiveForSymbols(): void
    {
        // Actif avec symbole en minuscules
        $btc = $this->makeAsset('Bitcoin', 'btc', 40_000.0);
        $this->assetRepository->method('findAll')->willReturn([$btc]);
        $this->currencyService->method('getUsdTndRate')->willReturn(3.0);

        $fakeResponse = $this->createMock(ResponseInterface::class);
        $fakeResponse->method('toArray')->willReturn(['bitcoin' => ['usd' => 50_000.0]]);
        $this->httpClient->method('request')->willReturn($fakeResponse);
        $this->em->method('flush');

        $service = $this->createService();
        $count   = $service->syncAssetPrices();

        // BTC (minuscule) doit quand même être reconnu et mis à jour
        $this->assertSame(1, $count);
        $this->assertEqualsWithDelta(150_000.0, $btc->getValue(), 0.01); // 50 000 × 3.0
    }
}
