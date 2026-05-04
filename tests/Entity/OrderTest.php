<?php

namespace App\Tests\Entity;

use App\Entity\Order;
use App\Entity\Asset;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Order
 * =============================================================================
 *
 *  Une entité Symfony (doctrine) est une simple classe PHP avec des propriétés
 *  et des getters/setters. Les tests d'entités vérifient :
 *    1. Le comportement des getters/setters (fluent interface = return $this)
 *    2. Les règles métier encodées dans l'entité
 *    3. Les valeurs par défaut à la construction
 *
 *  PAS BESOIN DE BDD : on crée l'entité directement avec `new Order()`.
 *  C'est un test purement "en mémoire".
 *
 *  CE QU'ON TESTE ICI :
 *    - Que les setters stockent bien les valeurs
 *    - Que les getters retournent bien les valeurs
 *    - Que l'interface fluent (chaînable) fonctionne ($order->setType('BUY')->setQuantity(5))
 *    - Que les valeurs initiales sont null (avant d'être définies)
 * =============================================================================
 */
class OrderTest extends TestCase
{
    private Order $order;

    protected function setUp(): void
    {
        // Pas de BDD : instanciation directe, propre avant chaque test
        $this->order = new Order();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut (null)
    // =========================================================================

    /**
     * Test : À la création, toutes les propriétés doivent être null.
     * C'est important car ça confirme que Doctrine n'a pas encore persisté cet objet.
     */
    public function testNewOrderHasNullValues(): void
    {
        $this->assertNull($this->order->getId(),       'id doit être null avant persistance');
        $this->assertNull($this->order->getAsset(),    'asset doit être null');
        $this->assertNull($this->order->getUser(),     'user doit être null');
        $this->assertNull($this->order->getQuantity(), 'quantity doit être null');
        $this->assertNull($this->order->getPrice(),    'price doit être null');
        $this->assertNull($this->order->getType(),     'type doit être null');
    }

    // =========================================================================
    //  Test 2 : Setters stockent les valeurs
    // =========================================================================

    /**
     * Test : setType() stocke la valeur et getType() la retourne correctement.
     */
    public function testSetAndGetType(): void
    {
        $this->order->setType('BUY');
        $this->assertSame('BUY', $this->order->getType());

        $this->order->setType('SELL');
        $this->assertSame('SELL', $this->order->getType());
    }

    /**
     * Test : setQuantity() et getQuantity() fonctionnent correctement.
     */
    public function testSetAndGetQuantity(): void
    {
        $this->order->setQuantity(100);
        $this->assertSame(100, $this->order->getQuantity());
    }

    /**
     * Test : setPrice() et getPrice() fonctionnent avec des floats.
     */
    public function testSetAndGetPrice(): void
    {
        $this->order->setPrice(45321.99);
        $this->assertSame(45321.99, $this->order->getPrice());
    }

    /**
     * Test : setAsset() lie un objet Asset à l'Order.
     */
    public function testSetAndGetAsset(): void
    {
        $asset = new Asset();
        $asset->setName('Bitcoin')->setSymbol('BTC')->setValue(50000.0)->setType('Crypto');

        $this->order->setAsset($asset);

        $this->assertSame($asset, $this->order->getAsset());
        $this->assertSame('BTC', $this->order->getAsset()->getSymbol());
    }

    /**
     * Test : setUser() lie un objet User à l'Order.
     */
    public function testSetAndGetUser(): void
    {
        $user = new User();
        $user->setEmail('alice@nexora.io')->setFullName('Alice Dupont');

        $this->order->setUser($user);

        $this->assertSame($user,             $this->order->getUser());
        $this->assertSame('alice@nexora.io', $this->order->getUser()->getEmail());
    }

    // =========================================================================
    //  Test 3 : Interface fluent (chaînable)
    // =========================================================================

    /**
     * Test : Tous les setters retournent `$this` (le même objet Order).
     * Cela permet le chaînage : $order->setType('BUY')->setQuantity(5)->setPrice(1000.0)
     */
    public function testSettersAreFluentChainable(): void
    {
        $asset = new Asset();
        $user  = new User();

        // On chaîne tous les setters d'un coup
        $result = $this->order
            ->setType('BUY')
            ->setQuantity(10)
            ->setPrice(99.99)
            ->setAsset($asset)
            ->setUser($user);

        // Le résultat de la chaîne doit être le même objet
        $this->assertSame($this->order, $result);

        // Et toutes les valeurs doivent être présentes
        $this->assertSame('BUY',   $this->order->getType());
        $this->assertSame(10,      $this->order->getQuantity());
        $this->assertSame(99.99,   $this->order->getPrice());
    }

    // =========================================================================
    //  Test 4 : Scénario complet (Order BUY réaliste)
    // =========================================================================

    /**
     * Test : Construction d'un ordre d'achat complet et vérification de tous
     * les champs. Simule ce qu'un controller ferait avant de persister.
     */
    public function testCompleteOrderBuyScenario(): void
    {
        // Arrange
        $asset = new Asset();
        $asset->setName('Ethereum')->setSymbol('ETH')->setValue(3200.0)->setType('Crypto');

        $user = new User();
        $user->setEmail('trader@nexora.io')->setFullName('Bob Trader');

        // Act : construction d'un ordre d'achat de 5 ETH à 3200 TND
        $this->order
            ->setAsset($asset)
            ->setUser($user)
            ->setType('BUY')
            ->setQuantity(5)
            ->setPrice(3200.0);

        // Assert : tout est cohérent
        $this->assertSame('ETH',   $this->order->getAsset()->getSymbol());
        $this->assertSame('BUY',   $this->order->getType());
        $this->assertSame(5,       $this->order->getQuantity());
        $this->assertSame(3200.0,  $this->order->getPrice());

        // Calcul du total (logique externe possible) : 5 * 3200 = 16 000
        $expectedTotal = $this->order->getQuantity() * $this->order->getPrice();
        $this->assertSame(16_000.0, $expectedTotal);
    }

    /**
     * Test : Un ordre SELL fonctionne de la même façon.
     */
    public function testOrderSellType(): void
    {
        $this->order->setType('SELL')->setQuantity(3)->setPrice(1500.0);

        $this->assertSame('SELL', $this->order->getType());
        $this->assertSame(4500.0, $this->order->getQuantity() * $this->order->getPrice());
    }
}
