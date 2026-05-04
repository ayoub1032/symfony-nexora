<?php

namespace App\Tests\Service;

use App\Service\SentimentAiService;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — SentimentAiService
 * =============================================================================
 *
 *  Ce service est le cœur de l'IA Nexora. Il implémente un classificateur
 *  Naive Bayes pour analyser le sentiment financier d'un portefeuille.
 *
 *  PRINCIPE D'UN TEST UNITAIRE :
 *    - On teste UNE seule classe, de façon isolée (pas de base de données, pas
 *      de réseau, pas d'autres services).
 *    - Chaque méthode de test commence par "test" ou a l'attribut #[Test].
 *    - Un test suit le pattern AAA : Arrange → Act → Assert
 *        • Arrange  : préparer les données d'entrée
 *        • Act      : appeler la méthode testée
 *        • Assert   : vérifier le résultat
 *
 *  STRUCTURE DES TESTS ICI :
 *    1. classify()  – vérifier que le classifieur renvoie le bon sentiment
 *    2. getPortfolioAdvice() – vérifier la logique métier complète
 * =============================================================================
 */
class SentimentAiServiceTest extends TestCase
{
    /**
     * Le service à tester. Instancié UNE fois pour tous les tests de cette classe.
     */
    private SentimentAiService $service;

    /**
     * setUp() est automatiquement appelé par PHPUnit avant CHAQUE test.
     * C'est ici qu'on crée une instance fraîche du service.
     */
    protected function setUp(): void
    {
        // SentimentAiService n'a aucune dépendance (pas d'injection),
        // on peut l'instancier directement sans mock.
        $this->service = new SentimentAiService();
    }

    // =========================================================================
    //  SECTION 1 : Tests de classify()
    // =========================================================================

    /**
     * Test : Un texte contenant des mots très haussiers doit être classifié BULLISH.
     *
     * Le modèle est entraîné sur des mots comme "surge", "moon", "rally"…
     * On s'attend donc à ce que ce texte soit BULLISH.
     */
    public function testClassifyReturnsBullishForBullishText(): void
    {
        // Arrange : texte clairement positif / haussier
        $text = 'massive surge bullish rally profit growth';

        // Act
        $result = $this->service->classify($text);

        // Assert : doit être BULLISH
        $this->assertSame('BULLISH', $result);
    }

    /**
     * Test : Un texte baissier doit retourner BEARISH.
     */
    public function testClassifyReturnsBearishForBearishText(): void
    {
        // Arrange : termes typiquement négatifs en finance
        $text = 'crash dump bearish panic sell liquidation';

        $result = $this->service->classify($text);

        $this->assertSame('BEARISH', $result);
    }

    /**
     * Test : Un texte neutre/stable doit retourner NEUTRAL.
     */
    public function testClassifyReturnsNeutralForNeutralText(): void
    {
        // Arrange : termes de consolidation / marché plat
        $text = 'sideways consolidation stable range';

        $result = $this->service->classify($text);

        $this->assertSame('NEUTRAL', $result);
    }

    /**
     * Test : La méthode classify() doit toujours retourner une des 3 valeurs
     * valides, même pour un texte vide ou un texte inconnu.
     */
    public function testClassifyAlwaysReturnsAValidCategory(): void
    {
        $validCategories = ['BULLISH', 'BEARISH', 'NEUTRAL'];

        // Texte vide → Naive Bayes retourne la catégorie de plus haute probabilité a priori
        $result = $this->service->classify('');
        $this->assertContains($result, $validCategories, 'classify("") doit retourner une catégorie valide');

        // Texte aléatoire inconnu
        $result2 = $this->service->classify('zzz xkcd foo bar');
        $this->assertContains($result2, $validCategories, 'classify(unknown) doit retourner une catégorie valide');
    }

    /**
     * Test : classify() est insensible à la casse.
     * "SURGE" et "surge" doivent donner le même résultat.
     */
    public function testClassifyIsCaseInsensitive(): void
    {
        $lower = $this->service->classify('surge moon rally');
        $upper = $this->service->classify('SURGE MOON RALLY');

        $this->assertSame($lower, $upper, 'classify() doit ignorer la casse');
    }

    // =========================================================================
    //  SECTION 2 : Tests de getPortfolioAdvice()
    // =========================================================================

    /**
     * Test : Si le portefeuille est vide (totalValue = 0), on reçoit le conseil
     * "portefeuille vide" avec sentiment NEUTRAL.
     */
    public function testGetPortfolioAdviceWithEmptyPortfolio(): void
    {
        // Arrange
        $assets     = [];
        $totalValue = 0.0;

        // Act
        $result = $this->service->getPortfolioAdvice($assets, $totalValue);

        // Assert : structure + valeurs
        $this->assertIsArray($result, 'Le résultat doit être un tableau');
        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('advice', $result);
        $this->assertSame('NEUTRAL', $result['sentiment']);
        $this->assertStringContainsString('empty', $result['advice']);
    }

    /**
     * Test : Un portefeuille concentré (1 seul actif à >50 % de la valeur totale)
     * doit forcer le sentiment BEARISH et indiquer un risque élevé.
     */
    public function testGetPortfolioAdviceHighConcentrationIsBearish(): void
    {
        // Arrange : BTC représente 80% de la valeur totale → risque élevé
        $assets = [
            ['symbol' => 'BTC', 'quantity' => 8, 'price' => 10_000.0],
            ['symbol' => 'ETH', 'quantity' => 1, 'price' => 2_000.0],
        ];
        // Valeur totale = 80 000 + 2 000 = 82 000
        // BTC poids = 80 000 / 82 000 ≈ 97 % > 50 %
        $totalValue = 82_000.0;

        // Act
        $result = $this->service->getPortfolioAdvice($assets, $totalValue);

        // Assert
        $this->assertSame('BEARISH', $result['sentiment'], 'Forte concentration → BEARISH forcé');
        $this->assertSame('High', $result['risk_level'], 'Risque doit être High');
        $this->assertStringContainsString('BTC', $result['advice'], 'L\'actif concentré doit être mentionné');
        $this->assertStringContainsString('concentration', strtolower($result['advice']));
    }

    /**
     * Test : Un portefeuille avec un seul actif (mais poids ≤ 50%) doit donner
     * un sentiment NEUTRAL et un risque Medium.
     */
    public function testGetPortfolioAdviceSingleAssetIsMediumRisk(): void
    {
        // Arrange : 1 actif seulement, valeur totale = valeur de l'actif
        $assets = [
            ['symbol' => 'ETH', 'quantity' => 1, 'price' => 1000.0],
        ];
        $totalValue = 1000.0;
        // poids ETH = 100 % > 50 % → cas BEARISH/High, pas Medium
        // Pour déclencher le cas < 2 actifs mais ≤ 50 %, on doit avoir poids ≤ 0.5
        // Ce cas n'est possible que si totalValue > 2 * valeur_actif
        // → on passe totalValue plus grande pour simuler 1 seul actif mais poids faible
        // En fait, avec 1 seul actif, le poids sera TOUJOURS 100% > 50%.
        // La logique code: if ($maxWeight > 0.5) => BEARISH/High
        //                  elseif ($assetCount < 2) => NEUTRAL/Medium
        // On ne peut atteindre la 2ème branche qu'avec 1 actif et poids ≤ 50%.
        // Cela se produit uniquement si totalValue a été gonflé artificiellement.
        $assets2 = [
            ['symbol' => 'ETH', 'quantity' => 1, 'price' => 500.0],
        ];
        $result = $this->service->getPortfolioAdvice($assets2, 2_000.0);

        // poids ETH = 500 / 2000 = 25 % ≤ 50 %, assetCount = 1 < 2 → NEUTRAL/Medium
        $this->assertSame('NEUTRAL', $result['sentiment']);
        $this->assertSame('Medium', $result['risk_level']);
    }

    /**
     * Test : Un portefeuille diversifié (≥ 3 actifs, aucun > 50 %) doit avoir
     * un risque Low et un sentiment non-vide.
     */
    public function testGetPortfolioAdviceWellDiversifiedIsLowRisk(): void
    {
        // Arrange : 3 actifs répartis équitablement
        $assets = [
            ['symbol' => 'BTC',  'quantity' => 1, 'price' => 3000.0],
            ['symbol' => 'ETH',  'quantity' => 2, 'price' => 1000.0],
            ['symbol' => 'LINK', 'quantity' => 5, 'price'  => 200.0],
        ];
        // Valeurs : 3000, 2000, 1000 → total = 6000
        // poids max = 3000/6000 = 50 % → condition est > 0.5 (strictement), donc ce n'est PAS High
        // assetCount = 3, donc la branche Medium ne s'active pas
        $totalValue = 6000.0;

        // Act
        $result = $this->service->getPortfolioAdvice($assets, $totalValue);

        // Assert
        $this->assertSame('Low', $result['risk_level']);
        $this->assertNotEmpty($result['advice']);
        $this->assertNotEmpty($result['sentiment']);
    }

    /**
     * Test : La structure de retour de getPortfolioAdvice() contient toujours
     * les clés 'sentiment', 'advice', et 'risk_level'.
     */
    public function testGetPortfolioAdviceAlwaysReturnsExpectedKeys(): void
    {
        $assets = [
            ['symbol' => 'BTC', 'quantity' => 1, 'price' => 50000.0],
        ];

        $result = $this->service->getPortfolioAdvice($assets, 50000.0);

        $this->assertArrayHasKey('sentiment',   $result, 'La clé "sentiment" doit être présente');
        $this->assertArrayHasKey('advice',      $result, 'La clé "advice" doit être présente');
        $this->assertArrayHasKey('risk_level',  $result, 'La clé "risk_level" doit être présente');
    }

    /**
     * Test : Le pourcentage de concentration affiché dans le conseil correspond
     * à la vraie valeur calculée (test de précision).
     */
    public function testGetPortfolioAdviceConcentrationPercentageIsCorrect(): void
    {
        // BTC = 75 % du portefeuille total
        $assets = [
            ['symbol' => 'BTC', 'quantity' => 3, 'price' => 10000.0], // 30000
            ['symbol' => 'ETH', 'quantity' => 1, 'price' => 10000.0], // 10000
        ];
        $totalValue = 40000.0; // BTC = 75 %

        $result = $this->service->getPortfolioAdvice($assets, $totalValue);

        // Le conseil doit mentionner "75" %
        $this->assertStringContainsString('75', $result['advice']);
    }
}
