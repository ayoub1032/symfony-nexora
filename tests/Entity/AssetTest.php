<?php

namespace App\Tests\Entity;

use App\Entity\Asset;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Asset
 * =============================================================================
 *
 *  Asset est une entité simple (pas de logique métier complexe) qui représente
 *  un actif financier (BTC, ETH, etc.).
 *
 *  ON TESTE :
 *    1. Valeurs par défaut (null avant toute assignation)
 *    2. Chaque getter/setter individuellement
 *    3. L'interface fluent (setters chaînables)
 *    4. Un scénario de création complet (comme le ferait un controller)
 *
 *  POURQUOI TESTER DES GETTERS/SETTERS SIMPLES ?
 *    → Pour détecter les erreurs de copier-coller (ex: setName stocke dans $symbol)
 *    → Pour servir de "filet de sécurité" lors de futurs refactorings
 *    → Pour garantir que la logique Doctrine (ORM) reste intacte
 * =============================================================================
 */
class AssetTest extends TestCase
{
    private Asset $asset;

    protected function setUp(): void
    {
        $this->asset = new Asset();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut (null)
    // =========================================================================

    /**
     * Test : Toutes les propriétés sont null avant d'être définies.
     */
    public function testNewAssetHasNullValues(): void
    {
        $this->assertNull($this->asset->getId(),     'id doit être null avant persistance Doctrine');
        $this->assertNull($this->asset->getName(),   'name doit être null');
        $this->assertNull($this->asset->getSymbol(), 'symbol doit être null');
        $this->assertNull($this->asset->getValue(),  'value doit être null');
        $this->assertNull($this->asset->getType(),   'type doit être null');
    }

    // =========================================================================
    //  Test 2 : setName() / getName()
    // =========================================================================

    /**
     * Test : setName() stocke la valeur et getName() la retourne exactement.
     */
    public function testSetAndGetName(): void
    {
        $this->asset->setName('Bitcoin');
        $this->assertSame('Bitcoin', $this->asset->getName());
    }

    /**
     * Test : setName() supporte les noms avec espaces et caractères spéciaux.
     */
    public function testSetNameWithSpecialCharacters(): void
    {
        $this->asset->setName('Avalanche (AVAX)');
        $this->assertSame('Avalanche (AVAX)', $this->asset->getName());
    }

    // =========================================================================
    //  Test 3 : setSymbol() / getSymbol()
    // =========================================================================

    /**
     * Test : setSymbol() et getSymbol() pour un symbole standard.
     */
    public function testSetAndGetSymbol(): void
    {
        $this->asset->setSymbol('BTC');
        $this->assertSame('BTC', $this->asset->getSymbol());
    }

    /**
     * Test : Chaque asset a un symbole différent (pas de confusion name/symbol).
     */
    public function testSymbolIsNotConfusedWithName(): void
    {
        $this->asset->setName('Ethereum');
        $this->asset->setSymbol('ETH');

        // Les deux doivent être indépendants
        $this->assertSame('Ethereum', $this->asset->getName());
        $this->assertSame('ETH',      $this->asset->getSymbol());
        $this->assertNotSame($this->asset->getName(), $this->asset->getSymbol());
    }

    // =========================================================================
    //  Test 4 : setValue() / getValue()
    // =========================================================================

    /**
     * Test : setValue() avec un float et getValue() le retourne intact.
     */
    public function testSetAndGetValue(): void
    {
        $this->asset->setValue(50000.99);
        $this->assertSame(50000.99, $this->asset->getValue());
    }

    /**
     * Test : setValue() accepte 0.0 (actif de valeur nulle).
     */
    public function testSetValueZero(): void
    {
        $this->asset->setValue(0.0);
        $this->assertSame(0.0, $this->asset->getValue());
    }

    /**
     * Test : setValue() accepte des très petits floats (pour des crypto-monnaies
     * de faible valeur comme DOGE ou SHIB).
     */
    public function testSetValueWithVerySmallFloat(): void
    {
        $this->asset->setValue(0.000001);
        $this->assertEqualsWithDelta(0.000001, $this->asset->getValue(), PHP_FLOAT_EPSILON);
    }

    // =========================================================================
    //  Test 5 : setType() / getType()
    // =========================================================================

    /**
     * Test : setType() et getType() pour différents types d'actifs.
     */
    public function testSetAndGetType(): void
    {
        $this->asset->setType('Crypto');
        $this->assertSame('Crypto', $this->asset->getType());
    }

    /**
     * Test : Le type peut être 'Stock', 'Crypto', 'ETF', etc.
     */
    public function testSetTypeWithDifferentValues(): void
    {
        foreach (['Crypto', 'Stock', 'ETF', 'Forex', 'Commodity'] as $type) {
            $this->asset->setType($type);
            $this->assertSame($type, $this->asset->getType(), "Type '$type' doit être stocké correctement");
        }
    }

    // =========================================================================
    //  Test 6 : Interface fluent
    // =========================================================================

    /**
     * Test : Les setters sont chaînables (retournent $this).
     * Cela permet : (new Asset())->setName('BTC')->setSymbol('BTC')->setValue(50000.0)
     */
    public function testSettersAreChainable(): void
    {
        $result = $this->asset
            ->setName('Solana')
            ->setSymbol('SOL')
            ->setValue(180.50)
            ->setType('Crypto');

        // Le résultat doit être le même objet Asset
        $this->assertSame($this->asset, $result, 'Les setters doivent retourner $this');
    }

    // =========================================================================
    //  Test 7 : Scénarios de création complets
    // =========================================================================

    /**
     * Test : Création complète d'un asset Bitcoin (scénario réaliste).
     */
    public function testCompleteBitcoinAsset(): void
    {
        $this->asset
            ->setName('Bitcoin')
            ->setSymbol('BTC')
            ->setValue(95000.0)
            ->setType('Crypto');

        $this->assertSame('Bitcoin', $this->asset->getName());
        $this->assertSame('BTC',     $this->asset->getSymbol());
        $this->assertSame(95000.0,   $this->asset->getValue());
        $this->assertSame('Crypto',  $this->asset->getType());

        // getId() reste null car on n'a pas persisté en BDD
        $this->assertNull($this->asset->getId());
    }

    /**
     * Test : Création d'un asset Ethereum.
     */
    public function testCompleteEthereumAsset(): void
    {
        $this->asset
            ->setName('Ethereum')
            ->setSymbol('ETH')
            ->setValue(3500.0)
            ->setType('Crypto');

        $this->assertSame('Ethereum', $this->asset->getName());
        $this->assertSame('ETH',      $this->asset->getSymbol());
        $this->assertSame(3500.0,     $this->asset->getValue());
    }

    /**
     * Test : Modification d'une valeur (mise à jour du prix après sync CoinGecko).
     */
    public function testUpdateAssetValue(): void
    {
        // Prix initial
        $this->asset->setName('BNB')->setSymbol('BNB')->setValue(400.0)->setType('Crypto');
        $this->assertSame(400.0, $this->asset->getValue());

        // Mise à jour après sync avec l'API
        $this->asset->setValue(450.75);
        $this->assertSame(450.75, $this->asset->getValue());
    }
}
