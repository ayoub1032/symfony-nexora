<?php

namespace App\Tests\Entity;

use App\Entity\Notification;
use App\Entity\Wallet;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité Notification
 * =============================================================================
 *
 *  Notification est un message envoyé à un utilisateur via son Wallet.
 *
 *  RÈGLES À TESTER :
 *    - Constructeur : createdAt auto-généré, isRead = false par défaut
 *    - type par défaut = 'info'
 *    - isRead() / setRead() → statut de lecture (non lu par défaut)
 *    - Le marquage "lu" est une action métier importante
 *
 *  POURQUOI TESTER isRead ?
 *    C'est une règle métier critique : une notification ne doit JAMAIS
 *    être marquée comme lue automatiquement. Elle commence toujours non lue.
 * =============================================================================
 */
class NotificationTest extends TestCase
{
    private Notification $notification;

    protected function setUp(): void
    {
        $this->notification = new Notification();
    }

    // =========================================================================
    //  Test 1 : Constructeur — Valeurs par défaut
    // =========================================================================

    /**
     * Test : createdAt est défini automatiquement à la création.
     */
    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertNotNull($this->notification->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->notification->getCreatedAt());
    }

    /**
     * Test : isRead est false par défaut (notification non lue à la création).
     *
     * RÈGLE MÉTIER : Une nouvelle notification est TOUJOURS non lue.
     * Si ce test échoue, cela signifie qu'une notification serait
     * marquée lue avant même d'être vue par l'utilisateur.
     */
    public function testNotificationIsUnreadByDefault(): void
    {
        $this->assertFalse($this->notification->isRead(),
            'Une nouvelle notification doit être non lue (isRead = false)');
    }

    /**
     * Test : Le type par défaut est 'info'.
     */
    public function testDefaultTypeIsInfo(): void
    {
        $this->assertSame('info', $this->notification->getType());
    }

    // =========================================================================
    //  Test 2 : Marquage comme "lu"
    // =========================================================================

    /**
     * Test : setRead(true) marque la notification comme lue.
     */
    public function testMarkAsRead(): void
    {
        $this->assertFalse($this->notification->isRead()); // Non lue initialement

        $this->notification->setRead(true);

        $this->assertTrue($this->notification->isRead(), 'Après setRead(true), isRead() doit être true');
    }

    /**
     * Test : setRead(false) peut remettre la notification en non lue.
     */
    public function testMarkAsUnread(): void
    {
        $this->notification->setRead(true);
        $this->assertTrue($this->notification->isRead());

        $this->notification->setRead(false);
        $this->assertFalse($this->notification->isRead(), 'setRead(false) doit remettre en non lu');
    }

    /**
     * Test : Appeler setRead(true) plusieurs fois est idempotent.
     */
    public function testMarkingAsReadMultipleTimesIsIdempotent(): void
    {
        $this->notification->setRead(true);
        $this->notification->setRead(true); // 2ème appel

        $this->assertTrue($this->notification->isRead());
    }

    // =========================================================================
    //  Test 3 : Getters / Setters
    // =========================================================================

    /**
     * Test : setMessage() et getMessage().
     */
    public function testSetAndGetMessage(): void
    {
        $this->notification->setMessage('Votre objectif "Bitcoin 2025" est atteint !');
        $this->assertSame('Votre objectif "Bitcoin 2025" est atteint !', $this->notification->getMessage());
    }

    /**
     * Test : setType() et getType() — les 4 types valides.
     */
    public function testSetTypeAcceptsAllValidTypes(): void
    {
        foreach (['info', 'success', 'warning', 'danger'] as $type) {
            $this->notification->setType($type);
            $this->assertSame($type, $this->notification->getType());
        }
    }

    /**
     * Test : setWallet() et getWallet().
     */
    public function testSetAndGetWallet(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Test User')->setBalance('500.00');

        $this->notification->setWallet($wallet);

        $this->assertSame($wallet, $this->notification->getWallet());
    }

    // =========================================================================
    //  Test 4 : Interface fluent
    // =========================================================================

    /**
     * Test : Les setters retournent $this (chaînables).
     */
    public function testSettersAreChainable(): void
    {
        $wallet = new Wallet();

        $result = $this->notification
            ->setWallet($wallet)
            ->setMessage('Alerte de solde bas')
            ->setType('warning')
            ->setRead(false);

        $this->assertSame($this->notification, $result);
    }

    // =========================================================================
    //  Test 5 : Scénarios complets
    // =========================================================================

    /**
     * Test : Notification de succès (objectif atteint) — cycle de vie complet.
     */
    public function testGoalAchievedNotificationLifecycle(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Alice')->setBalance('10000.00');

        // Créer la notification (non lue)
        $this->notification
            ->setWallet($wallet)
            ->setType('success')
            ->setMessage('Félicitations ! Votre objectif "Épargne 2025" est atteint.');

        $this->assertFalse($this->notification->isRead(), 'Pas encore lue');
        $this->assertSame('success', $this->notification->getType());

        // L'utilisateur consulte ses notifications
        $this->notification->setRead(true);

        $this->assertTrue($this->notification->isRead(), 'Maintenant lue');
        $this->assertStringContainsString('atteint', $this->notification->getMessage());
    }

    /**
     * Test : Notification d'alerte (deadline imminente) — danger non lu.
     */
    public function testUrgentDeadlineNotification(): void
    {
        $wallet = new Wallet();

        $this->notification
            ->setWallet($wallet)
            ->setType('danger')
            ->setMessage('URGENT : Il reste 2 jours pour atteindre votre objectif "Ferrari".');

        // Doit rester non lu par défaut
        $this->assertFalse($this->notification->isRead());
        $this->assertSame('danger', $this->notification->getType());
        $this->assertStringContainsString('URGENT', $this->notification->getMessage());
    }
}
