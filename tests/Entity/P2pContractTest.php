<?php

namespace App\Tests\Entity;

use App\Entity\P2pContract;
use App\Entity\User;
use App\Entity\Asset;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité P2pContract
 * =============================================================================
 *
 *  P2pContract représente un contrat de trading peer-to-peer.
 *  Les règles métier à tester :
 *
 *  RÈGLE 1 — Constructeur :
 *    • createdAt est automatiquement défini à `new \DateTime()`
 *    • status est automatiquement défini à 'OPEN'
 *
 *  RÈGLE 2 — Cycle de vie du contrat :
 *    OPEN → ACCEPTED → COMPLETED (chemin normal)
 *    OPEN → CANCELLED (annulation)
 *    On simule ce cycle complet en changeant les statuts.
 *
 *  RÈGLE 3 — Calcul du montant total :
 *    Le montant total d'un contrat = quantity * pricePerUnit
 *    (logique externe vérifiée via les getters)
 * =============================================================================
 */
class P2pContractTest extends TestCase
{
    private P2pContract $contract;

    protected function setUp(): void
    {
        $this->contract = new P2pContract();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut du constructeur
    // =========================================================================

    /**
     * Test : Le constructeur initialise createdAt et status automatiquement.
     * C'est crucial : un contrat créé doit toujours avoir un statut et une date.
     */
    public function testConstructorSetsDefaultValues(): void
    {
        // createdAt doit être défini (DateTime)
        $this->assertNotNull($this->contract->getCreatedAt(), 'createdAt doit être défini à la création');
        $this->assertInstanceOf(\DateTimeInterface::class, $this->contract->getCreatedAt());

        // Le statut initial est OPEN
        $this->assertSame('OPEN', $this->contract->getStatus(), 'Le statut initial doit être OPEN');
    }

    /**
     * Test : acceptedAt et completedAt sont null par défaut (pas encore traités).
     */
    public function testAcceptedAtAndCompletedAtAreNullByDefault(): void
    {
        $this->assertNull($this->contract->getAcceptedAt(),   'acceptedAt doit être null initialement');
        $this->assertNull($this->contract->getCompletedAt(),  'completedAt doit être null initialement');
        $this->assertNull($this->contract->getAcceptor(),     'acceptor doit être null initialement');
    }

    // =========================================================================
    //  Test 2 : Cycle de vie OPEN → ACCEPTED → COMPLETED
    // =========================================================================

    /**
     * Test : Simulation d'un contrat P2P complet du début à la fin.
     *
     * Étapes :
     *   1. Créateur ouvre un contrat (OPEN)
     *   2. Un accepteur l'accepte (ACCEPTED + acceptedAt)
     *   3. Le contrat est finalisé (COMPLETED + completedAt)
     */
    public function testContractLifecycleFromOpenToCompleted(): void
    {
        // Arrange : créer les entités associées
        $creator  = (new User())->setEmail('creator@nexora.io')->setFullName('Creator');
        $acceptor = (new User())->setEmail('acceptor@nexora.io')->setFullName('Acceptor');
        $asset    = (new Asset())->setName('Bitcoin')->setSymbol('BTC')->setValue(50000.0)->setType('Crypto');

        // Étape 1 — Création du contrat
        $this->contract
            ->setCreator($creator)
            ->setAsset($asset)
            ->setContractType('SELL')
            ->setQuantity(2)
            ->setPricePerUnit(48000.0);

        $this->assertSame('OPEN', $this->contract->getStatus());

        // Étape 2 — Acceptation
        $acceptedAt = new \DateTime();
        $this->contract
            ->setStatus('ACCEPTED')
            ->setAcceptor($acceptor)
            ->setAcceptedAt($acceptedAt);

        $this->assertSame('ACCEPTED',  $this->contract->getStatus());
        $this->assertSame($acceptor,   $this->contract->getAcceptor());
        $this->assertSame($acceptedAt, $this->contract->getAcceptedAt());

        // Étape 3 — Complétion
        $completedAt = new \DateTime();
        $this->contract
            ->setStatus('COMPLETED')
            ->setCompletedAt($completedAt);

        $this->assertSame('COMPLETED', $this->contract->getStatus());
        $this->assertSame($completedAt, $this->contract->getCompletedAt());
    }

    /**
     * Test : Un contrat peut être annulé depuis OPEN.
     */
    public function testContractCanBeCancelled(): void
    {
        $this->assertSame('OPEN', $this->contract->getStatus());

        $this->contract->setStatus('CANCELLED');

        $this->assertSame('CANCELLED', $this->contract->getStatus());
        // acceptedAt et completedAt restent null
        $this->assertNull($this->contract->getAcceptedAt());
        $this->assertNull($this->contract->getCompletedAt());
    }

    // =========================================================================
    //  Test 3 : Getters / Setters de base
    // =========================================================================

    /**
     * Test : setQuantity() et getQuantity().
     */
    public function testSetAndGetQuantity(): void
    {
        $this->contract->setQuantity(5);
        $this->assertSame(5, $this->contract->getQuantity());
    }

    /**
     * Test : setPricePerUnit() et getPricePerUnit().
     */
    public function testSetAndGetPricePerUnit(): void
    {
        $this->contract->setPricePerUnit(3200.50);
        $this->assertSame(3200.50, $this->contract->getPricePerUnit());
    }

    /**
     * Test : setContractType() et getContractType() → BUY ou SELL.
     */
    public function testSetAndGetContractType(): void
    {
        $this->contract->setContractType('BUY');
        $this->assertSame('BUY', $this->contract->getContractType());

        $this->contract->setContractType('SELL');
        $this->assertSame('SELL', $this->contract->getContractType());
    }

    /**
     * Test : setAsset() et getAsset().
     */
    public function testSetAndGetAsset(): void
    {
        $asset = (new Asset())->setName('Ethereum')->setSymbol('ETH')->setValue(3000.0)->setType('Crypto');
        $this->contract->setAsset($asset);

        $this->assertSame($asset, $this->contract->getAsset());
        $this->assertSame('ETH', $this->contract->getAsset()->getSymbol());
    }

    // =========================================================================
    //  Test 4 : Calcul du montant total (logique externe)
    // =========================================================================

    /**
     * Test : Le montant total d'un contrat = quantity * pricePerUnit.
     *        Cette logique est calculée par le contrôleur, mais on peut
     *        vérifier que les getters retournent les bonnes valeurs pour ce calcul.
     *
     *        Ex: 3 ETH à 3200 TND = 9600 TND total
     */
    public function testTotalAmountCalculation(): void
    {
        $this->contract->setQuantity(3)->setPricePerUnit(3200.0);

        $total = $this->contract->getQuantity() * $this->contract->getPricePerUnit();

        $this->assertSame(9600.0, $total);
    }

    // =========================================================================
    //  Test 5 : Interface fluent
    // =========================================================================

    /**
     * Test : Les setters sont chaînables (retournent $this).
     */
    public function testSettersAreChainable(): void
    {
        $creator = new User();
        $asset   = new Asset();

        $result = $this->contract
            ->setCreator($creator)
            ->setAsset($asset)
            ->setContractType('BUY')
            ->setQuantity(1)
            ->setPricePerUnit(50000.0)
            ->setStatus('OPEN');

        $this->assertSame($this->contract, $result);
    }

    // =========================================================================
    //  Test 6 : Scénario d'un contrat BUY entre 2 utilisateurs
    // =========================================================================

    /**
     * Test : Scénario complet — Alice veut acheter 1 BTC à Bob pour 50 000 TND.
     */
    public function testFullP2pBuyContractScenario(): void
    {
        $alice = (new User())->setEmail('alice@nexora.io')->setFullName('Alice');
        $bob   = (new User())->setEmail('bob@nexora.io')->setFullName('Bob');
        $btc   = (new Asset())->setName('Bitcoin')->setSymbol('BTC')->setValue(50000.0)->setType('Crypto');

        // Alice crée un contrat pour acheter 1 BTC à 50 000 TND
        $this->contract
            ->setCreator($alice)
            ->setAsset($btc)
            ->setContractType('BUY')
            ->setQuantity(1)
            ->setPricePerUnit(50000.0);

        // Vérifications initiales
        $this->assertSame('OPEN',   $this->contract->getStatus());
        $this->assertSame($alice,   $this->contract->getCreator());
        $this->assertSame('BUY',    $this->contract->getContractType());
        $this->assertSame(1,        $this->contract->getQuantity());
        $this->assertSame(50000.0,  $this->contract->getPricePerUnit());

        // Bob accepte le contrat
        $this->contract
            ->setAcceptor($bob)
            ->setStatus('ACCEPTED')
            ->setAcceptedAt(new \DateTime());

        $this->assertSame($bob,       $this->contract->getAcceptor());
        $this->assertSame('ACCEPTED', $this->contract->getStatus());
        $this->assertNotNull($this->contract->getAcceptedAt());

        // Le contrat est complété (paiement reçu)
        $this->contract
            ->setStatus('COMPLETED')
            ->setCompletedAt(new \DateTime());

        $this->assertSame('COMPLETED', $this->contract->getStatus());
        $this->assertNotNull($this->contract->getCompletedAt());

        // Montant total : 1 BTC × 50 000 TND = 50 000 TND
        $this->assertSame(50000.0, $this->contract->getQuantity() * $this->contract->getPricePerUnit());
    }
}
