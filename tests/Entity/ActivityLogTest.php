<?php

namespace App\Tests\Entity;

use App\Entity\ActivityLog;
use App\Entity\Wallet;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité ActivityLog
 * =============================================================================
 *
 *  ActivityLog enregistre les actions faites dans un Wallet (dépôt, retrait…).
 *
 *  RÈGLES À TESTER :
 *    - Le constructeur auto-génère createdAt (DateTimeImmutable)
 *    - Le type par défaut est 'info'
 *    - Les 4 types valides : 'info', 'success', 'warning', 'danger'
 *    - L'interface fluent (setters chaînables)
 *
 *  POURQUOI TESTER LES VALEURS PAR DÉFAUT ?
 *    → Si un dev change la valeur par défaut de 'type' en 'unknown' par erreur,
 *      ce test échouera immédiatement et l'alertera.
 * =============================================================================
 */
class ActivityLogTest extends TestCase
{
    private ActivityLog $log;

    protected function setUp(): void
    {
        $this->log = new ActivityLog();
    }

    // =========================================================================
    //  Test 1 : Constructeur
    // =========================================================================

    /**
     * Test : createdAt est défini automatiquement à la création (non null).
     */
    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->log->getCreatedAt(), 'createdAt doit être défini dans le constructeur');
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->log->getCreatedAt());
    }

    /**
     * Test : Le type par défaut est 'info' (valeur définie dans la propriété PHP).
     */
    public function testDefaultTypeIsInfo(): void
    {
        $this->assertSame('info', $this->log->getType(), 'Le type par défaut doit être "info"');
    }

    // =========================================================================
    //  Test 2 : Getters / Setters
    // =========================================================================

    /**
     * Test : setMessage() et getMessage().
     */
    public function testSetAndGetMessage(): void
    {
        $this->log->setMessage('Dépôt de 500 TND effectué avec succès.');
        $this->assertSame('Dépôt de 500 TND effectué avec succès.', $this->log->getMessage());
    }

    /**
     * Test : setType() accepte les 4 valeurs valides.
     */
    public function testSetTypeAcceptsAllValidTypes(): void
    {
        $validTypes = ['info', 'success', 'warning', 'danger'];

        foreach ($validTypes as $type) {
            $this->log->setType($type);
            $this->assertSame($type, $this->log->getType(), "Le type '$type' doit être stocké");
        }
    }

    /**
     * Test : setWallet() lie un Wallet au log.
     */
    public function testSetAndGetWallet(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Test Owner');

        $this->log->setWallet($wallet);

        $this->assertSame($wallet, $this->log->getWallet());
    }

    /**
     * Test : setCreatedAt() permet de surcharger la date.
     */
    public function testSetCreatedAt(): void
    {
        $date = new \DateTimeImmutable('2025-01-15 10:30:00');
        $this->log->setCreatedAt($date);

        $this->assertSame($date, $this->log->getCreatedAt());
    }

    // =========================================================================
    //  Test 3 : Interface fluent
    // =========================================================================

    /**
     * Test : Les setters retournent $this (chaînables).
     */
    public function testSettersAreChainable(): void
    {
        $wallet = new Wallet();

        $result = $this->log
            ->setWallet($wallet)
            ->setType('success')
            ->setMessage('Retrait de 200 TND');

        $this->assertSame($this->log, $result);
    }

    // =========================================================================
    //  Test 4 : Scénario complet (log d'un dépôt réussi)
    // =========================================================================

    /**
     * Test : Création d'un ActivityLog complet pour un dépôt sur un wallet.
     */
    public function testCompleteDepositLogScenario(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Alice')->setBalance('1500.00');

        $this->log
            ->setWallet($wallet)
            ->setType('success')
            ->setMessage('Dépôt de 500.00 TND — Nouveau solde : 1500.00 TND');

        $this->assertSame('success', $this->log->getType());
        $this->assertStringContainsString('500.00', $this->log->getMessage());
        $this->assertSame($wallet, $this->log->getWallet());
        $this->assertNotNull($this->log->getCreatedAt());
    }

    /**
     * Test : Log d'une alerte (solde faible).
     */
    public function testLowBalanceWarningLog(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Bob')->setBalance('50.00');

        $this->log
            ->setWallet($wallet)
            ->setType('warning')
            ->setMessage('Solde faible : 50.00 TND. Pensez à recharger votre wallet.');

        $this->assertSame('warning', $this->log->getType());
        $this->assertStringContainsString('faible', $this->log->getMessage());
    }
}
