<?php

namespace App\Tests\Entity;

use App\Entity\Transaction;
use App\Entity\Wallet;
use App\Entity\Category;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Transaction
 * =============================================================================
 *
 *  Transaction représente un mouvement financier sur un Wallet ('In' ou 'Out').
 *
 *  RÈGLES À TESTER :
 *    - Le constructeur auto-génère createdAt
 *    - Le type par défaut est 'In'
 *    - Les 2 types valides : 'In' (crédit), 'Out' (débit)
 *    - La catégorie est optionnelle (nullable)
 *    - Calcul du sens de la transaction (In = crédit, Out = débit)
 *
 *  NOTE PÉDAGOGIQUE : "In" signifie que l'argent ENTRE dans le wallet
 *  (dépôt, gain), "Out" signifie que l'argent SORT (retrait, achat).
 * =============================================================================
 */
class TransactionTest extends TestCase
{
    private Transaction $transaction;

    protected function setUp(): void
    {
        $this->transaction = new Transaction();
    }

    // =========================================================================
    //  Test 1 : Constructeur
    // =========================================================================

    /**
     * Test : createdAt est initialisé automatiquement dans le constructeur.
     */
    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->transaction->getCreatedAt(), 'createdAt doit être défini à la création');
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->transaction->getCreatedAt());
    }

    /**
     * Test : Le type par défaut est 'In' (les transactions sont des entrées par défaut).
     */
    public function testDefaultTypeIsIn(): void
    {
        $this->assertSame('In', $this->transaction->getType(), 'Le type par défaut doit être "In"');
    }

    // =========================================================================
    //  Test 2 : Getters / Setters
    // =========================================================================

    /**
     * Test : setAmount() et getAmount() — le montant est une chaîne (DECIMAL Doctrine).
     */
    public function testSetAndGetAmount(): void
    {
        $this->transaction->setAmount('250.00');
        $this->assertSame('250.00', $this->transaction->getAmount());
    }

    /**
     * Test : setType() avec 'In' (dépôt / crédit).
     */
    public function testSetTypeIn(): void
    {
        $this->transaction->setType('In');
        $this->assertSame('In', $this->transaction->getType());
    }

    /**
     * Test : setType() avec 'Out' (retrait / débit).
     */
    public function testSetTypeOut(): void
    {
        $this->transaction->setType('Out');
        $this->assertSame('Out', $this->transaction->getType());
    }

    /**
     * Test : setWallet() et getWallet().
     */
    public function testSetAndGetWallet(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Alice')->setBalance('2000.00');

        $this->transaction->setWallet($wallet);

        $this->assertSame($wallet, $this->transaction->getWallet());
    }

    /**
     * Test : setCategory() et getCategory() — association optionnelle.
     */
    public function testSetAndGetCategory(): void
    {
        $category = new Category();
        $category->setName('Alimentation')->setIcon('fas fa-utensils')->setColor('#22c55e');

        $this->transaction->setCategory($category);

        $this->assertSame($category, $this->transaction->getCategory());
        $this->assertSame('Alimentation', $this->transaction->getCategory()->getName());
    }

    /**
     * Test : La catégorie peut être null (transaction sans catégorie).
     */
    public function testCategoryCanBeNull(): void
    {
        // Par défaut, pas de catégorie
        $this->assertNull($this->transaction->getCategory(), 'La catégorie doit être null par défaut');

        // On peut aussi la remettre à null explicitement
        $this->transaction->setCategory(null);
        $this->assertNull($this->transaction->getCategory());
    }

    // =========================================================================
    //  Test 3 : Interface fluent
    // =========================================================================

    /**
     * Test : Les setters retournent $this (chaînables).
     */
    public function testSettersAreChainable(): void
    {
        $wallet   = new Wallet();
        $category = new Category();

        $result = $this->transaction
            ->setWallet($wallet)
            ->setAmount('100.00')
            ->setType('Out')
            ->setCategory($category);

        $this->assertSame($this->transaction, $result);
    }

    // =========================================================================
    //  Test 4 : Scénarios complets
    // =========================================================================

    /**
     * Test : Transaction d'entrée (dépôt d'argent dans le wallet).
     *        Type 'In' = l'argent entre dans le wallet.
     */
    public function testIncomingTransactionScenario(): void
    {
        $wallet   = new Wallet();
        $category = new Category();
        $category->setName('Salaire');

        $this->transaction
            ->setWallet($wallet)
            ->setAmount('3000.00')
            ->setType('In')
            ->setCategory($category);

        $this->assertSame('3000.00', $this->transaction->getAmount());
        $this->assertSame('In',      $this->transaction->getType());
        $this->assertSame('Salaire', $this->transaction->getCategory()->getName());
        $this->assertNotNull($this->transaction->getCreatedAt());

        // En termes métier : montant positif car c'est un crédit
        $this->assertGreaterThan(0, (float)$this->transaction->getAmount());
    }

    /**
     * Test : Transaction de sortie (retrait / paiement depuis le wallet).
     *        Type 'Out' = l'argent sort du wallet.
     */
    public function testOutgoingTransactionScenario(): void
    {
        $wallet   = new Wallet();
        $category = new Category();
        $category->setName('Transport');

        $this->transaction
            ->setWallet($wallet)
            ->setAmount('45.50')
            ->setType('Out')
            ->setCategory($category);

        $this->assertSame('45.50',     $this->transaction->getAmount());
        $this->assertSame('Out',       $this->transaction->getType());
        $this->assertSame('Transport', $this->transaction->getCategory()->getName());
    }

    /**
     * Test : Transaction sans catégorie (catégorie optionnelle).
     */
    public function testTransactionWithoutCategory(): void
    {
        $wallet = new Wallet();

        $this->transaction
            ->setWallet($wallet)
            ->setAmount('500.00')
            ->setType('In');
        // Pas de setCategory() → reste null

        $this->assertSame('500.00', $this->transaction->getAmount());
        $this->assertNull($this->transaction->getCategory(), 'Pas de catégorie = null autorisé');
    }

    /**
     * Test : Deux transactions de types opposés (cohérence comptable).
     *        Une 'In' et une 'Out' doivent être distinguables.
     */
    public function testInAndOutTransactionsAreDistinguishable(): void
    {
        $txIn  = (new Transaction())->setAmount('1000.00')->setType('In');
        $txOut = (new Transaction())->setAmount('200.00')->setType('Out');

        $this->assertNotSame($txIn->getType(), $txOut->getType());
        $this->assertSame('In',  $txIn->getType());
        $this->assertSame('Out', $txOut->getType());
    }
}
