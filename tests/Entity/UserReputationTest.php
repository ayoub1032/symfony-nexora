<?php

namespace App\Tests\Entity;

use App\Entity\UserReputation;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité UserReputation
 * =============================================================================
 *
 *  UserReputation contient UNE règle métier clé à tester :
 *
 *  RÈGLE — getAverageRating() :
 *    Calcule la note moyenne = totalScore / ratingCount
 *    • Si ratingCount = 0 → retourne 0.0 (protection contre la division par zéro)
 *    • Sinon → totalScore / ratingCount (float)
 *
 *  On teste aussi les valeurs par défaut du constructeur implicite
 *  (les propriétés sont initialisées à 0 via les annotations Doctrine).
 * =============================================================================
 */
class UserReputationTest extends TestCase
{
    private UserReputation $reputation;

    protected function setUp(): void
    {
        $this->reputation = new UserReputation();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut
    // =========================================================================

    /**
     * Test : Les compteurs sont initialisés à 0 par défaut (selon les annotations ORM).
     */
    public function testDefaultValuesAreZero(): void
    {
        $this->assertSame(0, $this->reputation->getCompletedContracts());
        $this->assertSame(0, $this->reputation->getCanceledContracts());
        $this->assertSame(0, $this->reputation->getTotalScore());
        $this->assertSame(0, $this->reputation->getRatingCount());
    }

    // =========================================================================
    //  Test 2 : getAverageRating() — Règle métier anti-division par zéro
    // =========================================================================

    /**
     * Test : Quand ratingCount = 0, getAverageRating() doit retourner 0.0
     * et ne pas lancer de DivisionByZeroError.
     *
     * C'est une règle de sécurité critique : sans ce test, le bug de
     * division par zéro pourrait crasher l'application silencieusement.
     */
    public function testAverageRatingIsZeroWhenNoRatings(): void
    {
        // Arrange : aucune note donnée (ratingCount = 0 par défaut)
        // Act + Assert : ne doit pas lever d'exception
        $average = $this->reputation->getAverageRating();

        $this->assertSame(0.0, $average, 'Moyenne doit être 0.0 sans aucune note');
    }

    /**
     * Test : Avec un seul vote de 5 → moyenne = 5.0.
     */
    public function testAverageRatingWithSinglePerfectRating(): void
    {
        $this->reputation->setTotalScore(5)->setRatingCount(1);

        $this->assertSame(5.0, $this->reputation->getAverageRating());
    }

    /**
     * Test : Avec 3 votes de valeur totale 12 → moyenne = 4.0.
     *        (Ex: 5 + 4 + 3 = 12 / 3 = 4.0)
     */
    public function testAverageRatingWithMultipleRatings(): void
    {
        $this->reputation->setTotalScore(12)->setRatingCount(3);

        $this->assertSame(4.0, $this->reputation->getAverageRating());
    }

    /**
     * Test : La moyenne peut être un float non-entier.
     *        Ex: totalScore=7, ratingCount=3 → 7/3 ≈ 2.333...
     */
    public function testAverageRatingCanBeDecimal(): void
    {
        $this->reputation->setTotalScore(7)->setRatingCount(3);

        $avg = $this->reputation->getAverageRating();

        $this->assertIsFloat($avg);
        $this->assertEqualsWithDelta(2.333, $avg, 0.001);
    }

    /**
     * Test : totalScore = 0 avec ratingCount > 0 → moyenne = 0.0.
     *        (Tous ont voté 0 = très mauvaise réputation)
     */
    public function testAverageRatingIsZeroWhenAllVotesAreZero(): void
    {
        $this->reputation->setTotalScore(0)->setRatingCount(5);

        $this->assertSame(0.0, $this->reputation->getAverageRating());
    }

    // =========================================================================
    //  Test 3 : Setters et getters
    // =========================================================================

    /**
     * Test : setCompletedContracts() et getCompletedContracts().
     */
    public function testSetAndGetCompletedContracts(): void
    {
        $this->reputation->setCompletedContracts(42);
        $this->assertSame(42, $this->reputation->getCompletedContracts());
    }

    /**
     * Test : setCanceledContracts() et getCanceledContracts().
     */
    public function testSetAndGetCanceledContracts(): void
    {
        $this->reputation->setCanceledContracts(3);
        $this->assertSame(3, $this->reputation->getCanceledContracts());
    }

    /**
     * Test : setTotalScore() et getTotalScore().
     */
    public function testSetAndGetTotalScore(): void
    {
        $this->reputation->setTotalScore(250);
        $this->assertSame(250, $this->reputation->getTotalScore());
    }

    /**
     * Test : setRatingCount() et getRatingCount().
     */
    public function testSetAndGetRatingCount(): void
    {
        $this->reputation->setRatingCount(50);
        $this->assertSame(50, $this->reputation->getRatingCount());
    }

    /**
     * Test : L'interface fluent est bien implémentée (les setters retournent $this).
     */
    public function testSettersAreChainable(): void
    {
        $result = $this->reputation
            ->setCompletedContracts(10)
            ->setCanceledContracts(2)
            ->setTotalScore(45)
            ->setRatingCount(10);

        $this->assertSame($this->reputation, $result);
    }

    // =========================================================================
    //  Test 4 : Association avec User
    // =========================================================================

    /**
     * Test : setUser() et getUser() — on peut associer un User.
     */
    public function testSetAndGetUser(): void
    {
        $user = new User();
        $user->setEmail('trader@nexora.io')->setFullName('Alice');

        $this->reputation->setUser($user);

        $this->assertSame($user, $this->reputation->getUser());
    }

    // =========================================================================
    //  Test 5 : Scénario complet d'un trader actif
    // =========================================================================

    /**
     * Test : Simulation d'un utilisateur avec 25 contrats complétés, 5 annulés,
     * et une note totale de 100 sur 22 votants → moyenne de 4.55.
     */
    public function testCompleteTraderScenario(): void
    {
        $user = new User();
        $user->setEmail('pro@nexora.io')->setFullName('Pro Trader');

        $this->reputation
            ->setUser($user)
            ->setCompletedContracts(25)
            ->setCanceledContracts(5)
            ->setTotalScore(100)
            ->setRatingCount(22);

        // Vérifications
        $this->assertSame(25, $this->reputation->getCompletedContracts());
        $this->assertSame(5,  $this->reputation->getCanceledContracts());

        // Vérifier la moyenne : 100 / 22 ≈ 4.545...
        $this->assertEqualsWithDelta(4.545, $this->reputation->getAverageRating(), 0.01);
    }
}
