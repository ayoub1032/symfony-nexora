<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Transaction;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Category
 * =============================================================================
 *
 *  Category représente une catégorie de transaction (Alimentation, Transport…).
 *
 *  RÈGLES À TESTER :
 *    - Valeurs par défaut : icon = 'fas fa-tag', color = '#6366f1'
 *    - addTransaction() : ajoute ET synchronise la relation inverse
 *    - addTransaction() est idempotent (pas de doublons)
 *    - La collection transactions est vide à la création
 * =============================================================================
 */
class CategoryTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        $this->category = new Category();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut du constructeur
    // =========================================================================

    /**
     * Test : icon et color ont des valeurs par défaut définies dans la propriété PHP.
     */
    public function testDefaultValues(): void
    {
        $this->assertSame('fas fa-tag', $this->category->getIcon(),  'icon par défaut = "fas fa-tag"');
        $this->assertSame('#6366f1',    $this->category->getColor(), 'color par défaut = "#6366f1"');
    }

    /**
     * Test : name est null avant d'être défini.
     */
    public function testDefaultNameIsNull(): void
    {
        $this->assertNull($this->category->getName());
    }

    /**
     * Test : La collection transactions est vide à la création.
     */
    public function testTransactionsCollectionIsEmptyInitially(): void
    {
        $this->assertCount(0, $this->category->getTransactions());
    }

    // =========================================================================
    //  Test 2 : Getters / Setters
    // =========================================================================

    /**
     * Test : setName() et getName().
     */
    public function testSetAndGetName(): void
    {
        $this->category->setName('Alimentation');
        $this->assertSame('Alimentation', $this->category->getName());
    }

    /**
     * Test : setIcon() et getIcon() — icône FontAwesome.
     */
    public function testSetAndGetIcon(): void
    {
        $this->category->setIcon('fas fa-car');
        $this->assertSame('fas fa-car', $this->category->getIcon());
    }

    /**
     * Test : setIcon() accepte null (icône optionnelle).
     */
    public function testSetIconToNull(): void
    {
        $this->category->setIcon(null);
        $this->assertNull($this->category->getIcon());
    }

    /**
     * Test : setColor() et getColor() — couleur hexadécimale.
     */
    public function testSetAndGetColor(): void
    {
        $this->category->setColor('#22c55e');
        $this->assertSame('#22c55e', $this->category->getColor());
    }

    // =========================================================================
    //  Test 3 : addTransaction() — Relation bidirectionnelle
    // =========================================================================

    /**
     * Test : addTransaction() ajoute la transaction à la collection.
     */
    public function testAddTransactionIncreasesCount(): void
    {
        $tx = new Transaction();
        $tx->setAmount('100.00')->setType('Out');

        $this->category->addTransaction($tx);

        $this->assertCount(1, $this->category->getTransactions());
    }

    /**
     * Test : addTransaction() synchronise la relation inverse.
     * Après l'ajout, $tx->getCategory() pointe vers $category.
     */
    public function testAddTransactionSyncsInverseRelation(): void
    {
        $tx = new Transaction();
        $tx->setAmount('50.00')->setType('In');

        $this->category->addTransaction($tx);

        $this->assertSame($this->category, $tx->getCategory(),
            'addTransaction() doit synchroniser tx->category');
    }

    /**
     * Test : addTransaction() est idempotent — même objet pas ajouté deux fois.
     */
    public function testAddSameTransactionTwiceDoesNotDuplicate(): void
    {
        $tx = new Transaction();
        $tx->setAmount('75.00');

        $this->category->addTransaction($tx);
        $this->category->addTransaction($tx); // 2ème appel identique

        $this->assertCount(1, $this->category->getTransactions(),
            'La même transaction ne doit pas être ajoutée deux fois');
    }

    /**
     * Test : Plusieurs transactions différentes peuvent être ajoutées.
     */
    public function testAddMultipleTransactions(): void
    {
        $tx1 = (new Transaction())->setAmount('100.00')->setType('In');
        $tx2 = (new Transaction())->setAmount('50.00')->setType('Out');
        $tx3 = (new Transaction())->setAmount('200.00')->setType('In');

        $this->category->addTransaction($tx1);
        $this->category->addTransaction($tx2);
        $this->category->addTransaction($tx3);

        $this->assertCount(3, $this->category->getTransactions());
    }

    // =========================================================================
    //  Test 4 : Interface fluent + Scénarios réalistes
    // =========================================================================

    /**
     * Test : setters chaînables.
     */
    public function testSettersAreChainable(): void
    {
        $result = $this->category
            ->setName('Transport')
            ->setIcon('fas fa-bus')
            ->setColor('#f59e0b');

        $this->assertSame($this->category, $result);
    }

    /**
     * Test : Catégorie "Alimentation" complète avec 2 transactions.
     */
    public function testFoodCategoryScenario(): void
    {
        $this->category
            ->setName('Alimentation')
            ->setIcon('fas fa-utensils')
            ->setColor('#22c55e');

        $supermarche = (new Transaction())->setAmount('85.50')->setType('Out');
        $restaurant  = (new Transaction())->setAmount('32.00')->setType('Out');

        $this->category->addTransaction($supermarche);
        $this->category->addTransaction($restaurant);

        $this->assertSame('Alimentation', $this->category->getName());
        $this->assertCount(2, $this->category->getTransactions());

        // Les 2 transactions doivent pointer vers cette catégorie
        foreach ($this->category->getTransactions() as $tx) {
            $this->assertSame($this->category, $tx->getCategory());
        }
    }
}
