<?php

namespace App\Tests\Entity;

use App\Entity\Portfolio;
use App\Entity\PortfolioAsset;
use App\Entity\Asset;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Portfolio
 * =============================================================================
 *
 *  Portfolio contient UNE règle métier clé à tester :
 *
 *  RÈGLE — recalculateTotalValue() :
 *    Recalcule la valeur totale du portefeuille en sommant :
 *    quantity × asset.value  pour chaque PortfolioAsset.
 *
 *  ON TESTE AUSSI :
 *    - addPortfolioAsset() : évite les doublons + synchronise portfolio↔asset
 *    - removePortfolioAsset() : supprime + désynchronise la relation inverse
 *    - totalValue initialisé à 0.0 dans le constructeur
 *    - La collection portfolioAssets est vide à la création
 * =============================================================================
 */
class PortfolioTest extends TestCase
{
    private Portfolio $portfolio;

    protected function setUp(): void
    {
        $this->portfolio = new Portfolio();
    }

    // =========================================================================
    //  Helpers
    // =========================================================================

    /**
     * Crée un PortfolioAsset avec un actif et une quantité donnés.
     */
    private function makePortfolioAsset(string $symbol, float $price, int $qty, float $avgPrice): PortfolioAsset
    {
        $asset = new Asset();
        $asset->setName($symbol)->setSymbol($symbol)->setValue($price)->setType('Crypto');

        $pa = new PortfolioAsset();
        $pa->setAsset($asset)->setQuantity($qty)->setAvgPrice($avgPrice);

        return $pa;
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut du constructeur
    // =========================================================================

    /**
     * Test : À la création, totalValue = 0.0 et la collection est vide.
     */
    public function testConstructorDefaultValues(): void
    {
        $this->assertSame(0.0, $this->portfolio->getTotalValue(), 'totalValue doit être 0.0 par défaut');
        $this->assertCount(0, $this->portfolio->getPortfolioAssets(), 'La collection doit être vide');
    }

    // =========================================================================
    //  Test 2 : addPortfolioAsset()
    // =========================================================================

    /**
     * Test : addPortfolioAsset() ajoute l'entité à la collection.
     */
    public function testAddPortfolioAssetIncreasesCount(): void
    {
        $pa = $this->makePortfolioAsset('BTC', 50000.0, 1, 45000.0);

        $this->portfolio->addPortfolioAsset($pa);

        $this->assertCount(1, $this->portfolio->getPortfolioAssets());
    }

    /**
     * Test : addPortfolioAsset() synchronise la relation inverse (portfolio → pa).
     * Après l'ajout, $pa->getPortfolio() doit pointer vers $this->portfolio.
     */
    public function testAddPortfolioAssetSyncsInverseRelation(): void
    {
        $pa = $this->makePortfolioAsset('ETH', 3000.0, 2, 2800.0);

        $this->portfolio->addPortfolioAsset($pa);

        $this->assertSame($this->portfolio, $pa->getPortfolio(),
            'addPortfolioAsset() doit synchroniser la relation inverse');
    }

    /**
     * Test : addPortfolioAsset() n'ajoute pas le même objet deux fois (idempotent).
     */
    public function testAddSamePortfolioAssetTwiceDoesNotDuplicate(): void
    {
        $pa = $this->makePortfolioAsset('SOL', 150.0, 10, 120.0);

        $this->portfolio->addPortfolioAsset($pa);
        $this->portfolio->addPortfolioAsset($pa); // 2ème appel avec le même objet

        $this->assertCount(1, $this->portfolio->getPortfolioAssets(),
            'Le même PortfolioAsset ne doit pas être ajouté deux fois');
    }

    /**
     * Test : On peut ajouter plusieurs actifs différents.
     */
    public function testAddMultiplePortfolioAssets(): void
    {
        $pa1 = $this->makePortfolioAsset('BTC', 50000.0, 1, 45000.0);
        $pa2 = $this->makePortfolioAsset('ETH', 3000.0,  2, 2800.0);
        $pa3 = $this->makePortfolioAsset('SOL', 150.0,   5, 120.0);

        $this->portfolio->addPortfolioAsset($pa1);
        $this->portfolio->addPortfolioAsset($pa2);
        $this->portfolio->addPortfolioAsset($pa3);

        $this->assertCount(3, $this->portfolio->getPortfolioAssets());
    }

    // =========================================================================
    //  Test 3 : removePortfolioAsset()
    // =========================================================================

    /**
     * Test : removePortfolioAsset() retire l'élément de la collection.
     */
    public function testRemovePortfolioAssetDecreasesCount(): void
    {
        $pa = $this->makePortfolioAsset('BTC', 50000.0, 1, 45000.0);
        $this->portfolio->addPortfolioAsset($pa);
        $this->assertCount(1, $this->portfolio->getPortfolioAssets());

        $this->portfolio->removePortfolioAsset($pa);

        $this->assertCount(0, $this->portfolio->getPortfolioAssets());
    }

    /**
     * Test : removePortfolioAsset() désynchronise la relation inverse (pa→portfolio = null).
     */
    public function testRemovePortfolioAssetNullsInverseRelation(): void
    {
        $pa = $this->makePortfolioAsset('ETH', 3000.0, 1, 2500.0);
        $this->portfolio->addPortfolioAsset($pa);

        $this->portfolio->removePortfolioAsset($pa);

        $this->assertNull($pa->getPortfolio(),
            'removePortfolioAsset() doit mettre portfolio à null dans le PortfolioAsset');
    }

    /**
     * Test : Supprimer un élément qui n'est pas dans la collection ne cause pas d'erreur.
     */
    public function testRemoveNonExistentAssetDoesNotFail(): void
    {
        $pa = $this->makePortfolioAsset('LINK', 20.0, 100, 18.0);

        // Pa n'a pas été ajouté au portfolio, la suppression doit être silencieuse
        $this->portfolio->removePortfolioAsset($pa);

        // Pas d'exception, la collection reste vide
        $this->assertCount(0, $this->portfolio->getPortfolioAssets());
    }

    // =========================================================================
    //  Test 4 : recalculateTotalValue() — Règle Métier
    // =========================================================================

    /**
     * Test : Portfolio vide → totalValue reste 0.0.
     */
    public function testRecalculateTotalValueWithEmptyPortfolio(): void
    {
        $this->portfolio->recalculateTotalValue();

        $this->assertSame(0.0, $this->portfolio->getTotalValue());
    }

    /**
     * Test : 1 actif → totalValue = quantity × price.
     *        1 BTC à 50 000 TND → totalValue = 50 000 TND
     */
    public function testRecalculateTotalValueWithOneAsset(): void
    {
        $pa = $this->makePortfolioAsset('BTC', 50_000.0, 1, 45_000.0);
        $this->portfolio->addPortfolioAsset($pa);

        $this->portfolio->recalculateTotalValue();

        $this->assertSame(50_000.0, $this->portfolio->getTotalValue());
    }

    /**
     * Test : Plusieurs actifs → totalValue = somme de tous les (qty × price).
     *
     *  BTC : 1  × 50 000 = 50 000
     *  ETH : 5  ×  3 000 = 15 000
     *  SOL : 10 ×    150 =  1 500
     *  ─────────────────────────
     *  TOTAL               66 500 TND
     */
    public function testRecalculateTotalValueWithMultipleAssets(): void
    {
        $this->portfolio->addPortfolioAsset($this->makePortfolioAsset('BTC', 50_000.0,  1, 45_000.0));
        $this->portfolio->addPortfolioAsset($this->makePortfolioAsset('ETH',  3_000.0,  5,  2_800.0));
        $this->portfolio->addPortfolioAsset($this->makePortfolioAsset('SOL',    150.0, 10,    120.0));

        $this->portfolio->recalculateTotalValue();

        $this->assertEqualsWithDelta(66_500.0, $this->portfolio->getTotalValue(), 0.01);
    }

    /**
     * Test : recalculateTotalValue() met à jour la valeur si un prix change.
     *        Simule la mise à jour des prix via AssetPriceService.
     */
    public function testRecalculateTotalValueAfterPriceUpdate(): void
    {
        $asset = new Asset();
        $asset->setName('Bitcoin')->setSymbol('BTC')->setValue(50_000.0)->setType('Crypto');

        $pa = new PortfolioAsset();
        $pa->setAsset($asset)->setQuantity(2)->setAvgPrice(45_000.0);

        $this->portfolio->addPortfolioAsset($pa);
        $this->portfolio->recalculateTotalValue();

        // Valeur initiale : 2 × 50 000 = 100 000
        $this->assertSame(100_000.0, $this->portfolio->getTotalValue());

        // Simulation d'une mise à jour du prix (hausse à 60 000)
        $asset->setValue(60_000.0);
        $this->portfolio->recalculateTotalValue();

        // Nouvelle valeur : 2 × 60 000 = 120 000
        $this->assertSame(120_000.0, $this->portfolio->getTotalValue());
    }

    /**
     * Test : recalculateTotalValue() prend en compte uniquement les actifs présents.
     *        Après suppression d'un actif, la valeur doit diminuer.
     */
    public function testRecalculateTotalValueAfterRemovingAsset(): void
    {
        $pa1 = $this->makePortfolioAsset('BTC', 50_000.0, 1, 45_000.0); // 50 000
        $pa2 = $this->makePortfolioAsset('ETH',  3_000.0, 2,  2_500.0); //  6 000

        $this->portfolio->addPortfolioAsset($pa1);
        $this->portfolio->addPortfolioAsset($pa2);
        $this->portfolio->recalculateTotalValue();

        $this->assertSame(56_000.0, $this->portfolio->getTotalValue());

        // Suppression de l'actif ETH
        $this->portfolio->removePortfolioAsset($pa2);
        $this->portfolio->recalculateTotalValue();

        // Maintenant seulement BTC : 1 × 50 000 = 50 000
        $this->assertSame(50_000.0, $this->portfolio->getTotalValue());
    }

    // =========================================================================
    //  Test 5 : setTotalValue() et getTotalValue()
    // =========================================================================

    /**
     * Test : setTotalValue() et getTotalValue() de base.
     */
    public function testSetAndGetTotalValue(): void
    {
        $this->portfolio->setTotalValue(12_345.67);
        $this->assertSame(12_345.67, $this->portfolio->getTotalValue());
    }

    // =========================================================================
    //  Test 6 : Association avec User
    // =========================================================================

    /**
     * Test : setUser() et getUser().
     */
    public function testSetAndGetUser(): void
    {
        $user = new User();
        $user->setEmail('trader@nexora.io')->setFullName('Test Trader');

        $this->portfolio->setUser($user);

        $this->assertSame($user, $this->portfolio->getUser());
    }
}
