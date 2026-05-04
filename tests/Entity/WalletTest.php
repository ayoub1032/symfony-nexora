<?php

namespace App\Tests\Entity;

use App\Entity\Wallet;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Wallet
 * =============================================================================
 *
 *  L'entité Wallet contient deux RÈGLES MÉTIER importantes que l'on doit tester :
 *
 *  RÈGLE 1 — getUsdtBalance() :
 *    Convertit le solde TND en USDT en divisant par 3.10
 *    Ex: solde = 310 TND → getUsdtBalance() = 100.0 USDT
 *
 *  RÈGLE 2 — getEurBalance() :
 *    Convertit le solde TND en EUR en divisant par 3.35
 *    Ex: solde = 335 TND → getEurBalance() = 100.0 EUR
 *
 *  On teste aussi :
 *    - L'initialisation (createdAt généré automatiquement)
 *    - Les collections (walletGoals, activityLogs, etc.) initialisées vides
 *    - L'association bidirectionnelle avec User
 * =============================================================================
 */
class WalletTest extends TestCase
{
    private Wallet $wallet;

    protected function setUp(): void
    {
        $this->wallet = new Wallet();
    }

    // =========================================================================
    //  Test 1 : Initialisation du constructeur
    // =========================================================================

    /**
     * Test : À la création, createdAt est automatiquement défini (non null).
     * Le constructeur de Wallet fait `$this->createdAt = new \DateTimeImmutable()`.
     */
    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->wallet->getCreatedAt(), 'createdAt doit être défini à la création');
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->wallet->getCreatedAt());
    }

    /**
     * Test : Le solde initial est '0.00' (chaine de caractères pour DECIMAL Doctrine).
     */
    public function testDefaultBalanceIsZero(): void
    {
        $this->assertSame('0.00', $this->wallet->getBalance());
    }

    /**
     * Test : Les collections (goals, logs, notifications, transactions) sont
     * initialisées vides dans le constructeur.
     */
    public function testCollectionsAreInitializedEmpty(): void
    {
        $this->assertCount(0, $this->wallet->getWalletGoals(),   'walletGoals doit être vide');
        $this->assertCount(0, $this->wallet->getActivityLogs(),  'activityLogs doit être vide');
        $this->assertCount(0, $this->wallet->getNotifications(), 'notifications doit être vide');
    }

    // =========================================================================
    //  Test 2 : Getters / Setters de base
    // =========================================================================

    /**
     * Test : setOwner() et getOwner().
     */
    public function testSetAndGetOwner(): void
    {
        $this->wallet->setOwner('Alice Martin');
        $this->assertSame('Alice Martin', $this->wallet->getOwner());
    }

    /**
     * Test : setBalance() et getBalance().
     */
    public function testSetAndGetBalance(): void
    {
        $this->wallet->setBalance('1500.75');
        $this->assertSame('1500.75', $this->wallet->getBalance());
    }

    // =========================================================================
    //  Test 3 : Règle Métier 1 — Conversion TND → USDT
    // =========================================================================

    /**
     * Test : getUsdtBalance() = balance / 3.10
     *
     * Exemple : 310 TND / 3.10 = 100 USDT
     */
    public function testGetUsdtBalanceConversion(): void
    {
        $this->wallet->setBalance('310.00');

        $usdt = $this->wallet->getUsdtBalance();

        // On utilise assertEqualsWithDelta car les floats peuvent avoir des imprécisions
        $this->assertEqualsWithDelta(100.0, $usdt, 0.01, 'Conversion TND→USDT incorrecte');
    }

    /**
     * Test : getUsdtBalance() avec un solde à 0 → retourne 0.
     */
    public function testGetUsdtBalanceWithZeroBalance(): void
    {
        $this->wallet->setBalance('0.00');
        $this->assertSame(0.0, $this->wallet->getUsdtBalance());
    }

    /**
     * Test : getUsdtBalance() avec un grand solde.
     *        3100 TND / 3.10 = 1000 USDT
     */
    public function testGetUsdtBalanceWithLargeAmount(): void
    {
        $this->wallet->setBalance('3100.00');

        $this->assertEqualsWithDelta(1000.0, $this->wallet->getUsdtBalance(), 0.01);
    }

    // =========================================================================
    //  Test 4 : Règle Métier 2 — Conversion TND → EUR
    // =========================================================================

    /**
     * Test : getEurBalance() = balance / 3.35
     *
     * Exemple : 335 TND / 3.35 = 100 EUR
     */
    public function testGetEurBalanceConversion(): void
    {
        $this->wallet->setBalance('335.00');

        $eur = $this->wallet->getEurBalance();

        $this->assertEqualsWithDelta(100.0, $eur, 0.01, 'Conversion TND→EUR incorrecte');
    }

    /**
     * Test : getEurBalance() avec solde à 0 → retourne 0.
     */
    public function testGetEurBalanceWithZeroBalance(): void
    {
        $this->wallet->setBalance('0.00');
        $this->assertSame(0.0, $this->wallet->getEurBalance());
    }

    /**
     * Test : Les conversions USDT et EUR donnent des résultats différents
     * pour le même solde (taux différents).
     */
    public function testUsdtAndEurBalancesAreDifferent(): void
    {
        $this->wallet->setBalance('1000.00');

        $usdt = $this->wallet->getUsdtBalance(); // 1000 / 3.10 ≈ 322.58
        $eur  = $this->wallet->getEurBalance();  // 1000 / 3.35 ≈ 298.51

        $this->assertNotEquals($usdt, $eur, 'USDT et EUR ne doivent pas être égaux pour le même solde');
        $this->assertGreaterThan($eur, $usdt, 'USDT doit être > EUR (taux USDT plus faible)');
    }

    // =========================================================================
    //  Test 5 : Association avec User
    // =========================================================================

    /**
     * Test : setUser() et getUser() — association bidirectionnelle.
     */
    public function testSetAndGetUser(): void
    {
        $user = new User();
        $user->setEmail('test@nexora.io')->setFullName('Test User');

        $this->wallet->setUser($user);

        $this->assertSame($user, $this->wallet->getUser());
    }

    /**
     * Test : On peut dissocier le wallet en passant null.
     */
    public function testSetUserNullDisassociates(): void
    {
        $user = new User();
        $this->wallet->setUser($user);
        $this->wallet->setUser(null);

        $this->assertNull($this->wallet->getUser());
    }
}
