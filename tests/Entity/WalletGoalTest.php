<?php

namespace App\Tests\Entity;

use App\Entity\WalletGoal;
use App\Entity\Wallet;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité WalletGoal
 * =============================================================================
 *
 *  WalletGoal est l'entité la plus riche en logique métier du projet.
 *  Elle contient 3 règles métier importantes à tester :
 *
 *  RÈGLE 1 — getProgression() :
 *    Calcule le % d'avancement = (balance du wallet / targetAmount) * 100
 *    Plafonné à 100 (min 0).
 *
 *  RÈGLE 2 — getStatus() :
 *    Retourne le statut dynamique selon la progression et la date :
 *      • 'cancelled' si manuellement annulé
 *      • 'achieved'  si progression >= 100
 *      • 'expired'   si deadline passée
 *      • statut brut sinon ('in_progress')
 *
 *  RÈGLE 3 — isUrgent() :
 *    Vrai si la deadline est dans <= 7 jours ET objectif non atteint ET non annulé.
 *
 *  TECHNIQUE DE TEST "TIME-SENSITIVE" :
 *    Ces tests dépendent de la date courante (today). On les écrit de façon à
 *    toujours fonctionner en passant des dates RELATIVES (ex: +10 days, -1 day).
 * =============================================================================
 */
class WalletGoalTest extends TestCase
{
    /**
     * Crée un Wallet avec un solde donné.
     */
    private function makeWallet(string $balance): Wallet
    {
        $wallet = new Wallet();
        $wallet->setBalance($balance);
        return $wallet;
    }

    /**
     * Crée un WalletGoal complet avec les valeurs souhaitées.
     *
     * @param string $balance      Solde du wallet associé
     * @param string $targetAmount Montant cible de l'objectif
     * @param string $deadlineStr  Date de deadline au format '+Xdays' ou '-Xdays'
     */
    private function makeGoal(string $balance, string $targetAmount, string $deadlineStr = '+30 days'): WalletGoal
    {
        $goal = new WalletGoal();
        $goal->setWallet($this->makeWallet($balance));
        $goal->setName('Test Goal');
        $goal->setTargetAmount($targetAmount);
        $goal->setDeadline(new \DateTimeImmutable($deadlineStr));
        return $goal;
    }

    // =========================================================================
    //  Test 1 : Initialisation du constructeur
    // =========================================================================

    /**
     * Test : À la création, createdAt est défini et le statut est 'in_progress'.
     */
    public function testConstructorDefaults(): void
    {
        $goal = new WalletGoal();

        $this->assertNotNull($goal->getCreatedAt(), 'createdAt doit être défini');
        $this->assertInstanceOf(\DateTimeImmutable::class, $goal->getCreatedAt());
        // Le statut brut avant calcul dynamique
        $goal->setDeadline(new \DateTimeImmutable('+30 days'));
        $goal->setTargetAmount('1000');
        // Sans wallet → progression = 0 → in_progress
        $this->assertSame('in_progress', $goal->getStatus());
    }

    // =========================================================================
    //  Test 2 : getProgression() — Calcul du pourcentage
    // =========================================================================

    /**
     * Test : Sans wallet, la progression est 0.
     */
    public function testProgressionIsZeroWithoutWallet(): void
    {
        $goal = new WalletGoal();
        $goal->setTargetAmount('1000');

        $this->assertSame(0, $goal->getProgression());
    }

    /**
     * Test : Progression normale — 500 TND / 1000 TND cible = 50%.
     */
    public function testProgressionFiftyPercent(): void
    {
        $goal = $this->makeGoal('500', '1000');

        $this->assertSame(50, $goal->getProgression());
    }

    /**
     * Test : Progression à 100% quand le solde atteint exactement la cible.
     */
    public function testProgressionExactlyOneHundredPercent(): void
    {
        $goal = $this->makeGoal('1000', '1000');

        $this->assertSame(100, $goal->getProgression());
    }

    /**
     * Test : Progression plafonnée à 100 même si le solde dépasse la cible.
     * Ex: 2000 TND / 1000 TND cible → plafonné à 100 (pas 200).
     */
    public function testProgressionCappedAtOneHundred(): void
    {
        $goal = $this->makeGoal('2000', '1000');

        $this->assertSame(100, $goal->getProgression());
    }

    /**
     * Test : Progression ne peut pas être négative (min 0).
     */
    public function testProgressionCannotBeNegative(): void
    {
        $goal = $this->makeGoal('0', '1000');

        $this->assertSame(0, $goal->getProgression());
    }

    /**
     * Test : Target = 0 → pas de division par zéro, retourne 0.
     */
    public function testProgressionWithZeroTargetReturnsZero(): void
    {
        $goal = new WalletGoal();
        $goal->setWallet($this->makeWallet('500'));
        $goal->setTargetAmount('0'); // target invalide mais ne doit pas crasher

        $this->assertSame(0, $goal->getProgression());
    }

    // =========================================================================
    //  Test 3 : getStatus() — Logique de statut dynamique
    // =========================================================================

    /**
     * Test : Statut 'cancelled' est toujours retourné si setStatus('cancelled')
     * a été appelé, même si la progression est à 100%.
     */
    public function testStatusIsCancelledWhenManuallySet(): void
    {
        $goal = $this->makeGoal('2000', '1000'); // progression = 100%
        $goal->setStatus('cancelled');

        $this->assertSame('cancelled', $goal->getStatus());
    }

    /**
     * Test : Statut 'achieved' quand progression >= 100.
     */
    public function testStatusIsAchievedWhenProgressionIsOneHundred(): void
    {
        $goal = $this->makeGoal('1500', '1000', '+30 days'); // 150% → plafonné à 100

        $this->assertSame('achieved', $goal->getStatus());
    }

    /**
     * Test : Statut 'expired' si la deadline est passée et l'objectif non atteint.
     */
    public function testStatusIsExpiredWhenDeadlineIsPassed(): void
    {
        $goal = $this->makeGoal('200', '1000', '-1 day'); // deadline hier

        $this->assertSame('expired', $goal->getStatus());
    }

    /**
     * Test : Statut 'in_progress' si en cours (deadline future et progression < 100).
     */
    public function testStatusIsInProgressWhenOngoing(): void
    {
        $goal = $this->makeGoal('300', '1000', '+30 days'); // 30% et deadline OK

        $this->assertSame('in_progress', $goal->getStatus());
    }

    /**
     * Test : La priorité des statuts est correcte :
     * cancelled > achieved > expired > in_progress.
     * Un objectif annulé avec deadline passée → 'cancelled' (pas 'expired').
     */
    public function testCancelledTakesPriorityOverExpired(): void
    {
        $goal = $this->makeGoal('100', '1000', '-5 days'); // deadline passée
        $goal->setStatus('cancelled');

        // Doit retourner 'cancelled', pas 'expired'
        $this->assertSame('cancelled', $goal->getStatus());
    }

    // =========================================================================
    //  Test 4 : isUrgent() — Deadline imminente
    // =========================================================================

    /**
     * Test : isUrgent() = true si deadline dans <= 7 jours et objectif non atteint.
     */
    public function testIsUrgentWhenDeadlineWithinSevenDays(): void
    {
        $goal = $this->makeGoal('100', '1000', '+5 days'); // 5 jours restants, 10% progress

        $this->assertTrue($goal->isUrgent());
    }

    /**
     * Test : isUrgent() = false si deadline dans > 7 jours.
     */
    public function testIsNotUrgentWhenDeadlineFarAway(): void
    {
        $goal = $this->makeGoal('100', '1000', '+30 days');

        $this->assertFalse($goal->isUrgent());
    }

    /**
     * Test : isUrgent() = false si objectif est atteint (même avec deadline imminente).
     * Pas urgent si c'est déjà fait !
     */
    public function testIsNotUrgentWhenGoalIsAchieved(): void
    {
        $goal = $this->makeGoal('2000', '1000', '+3 days'); // 100% atteint ET 3 jours

        $this->assertFalse($goal->isUrgent(), 'Un objectif atteint ne doit jamais être urgent');
    }

    /**
     * Test : isUrgent() = false si le goal est annulé.
     */
    public function testIsNotUrgentWhenCancelled(): void
    {
        $goal = $this->makeGoal('100', '1000', '+2 days');
        $goal->setStatus('cancelled');

        $this->assertFalse($goal->isUrgent());
    }

    /**
     * Test : isUrgent() = false si pas de deadline définie.
     */
    public function testIsNotUrgentWithNoDeadline(): void
    {
        $goal = new WalletGoal();
        $goal->setWallet($this->makeWallet('100'));
        $goal->setTargetAmount('1000');
        $goal->setName('No deadline');
        // Pas de setDeadline() appelé

        $this->assertFalse($goal->isUrgent());
    }

    /**
     * Test : isUrgent() = false si la deadline est exactement aujourd'hui (7 jours = boundary).
     */
    public function testIsUrgentOnExactSevenDays(): void
    {
        $goal = $this->makeGoal('100', '1000', '+7 days'); // 7 jours pile

        // 7 jours → doit être urgent (condition: days <= 7)
        $this->assertTrue($goal->isUrgent());
    }

    // =========================================================================
    //  Test 5 : Getters / Setters de base
    // =========================================================================

    /**
     * Test : setName() et getName().
     */
    public function testSetAndGetName(): void
    {
        $goal = new WalletGoal();
        $goal->setName('Mon épargne Bitcoin');

        $this->assertSame('Mon épargne Bitcoin', $goal->getName());
    }

    /**
     * Test : setTargetAmount() et getTargetAmount().
     */
    public function testSetAndGetTargetAmount(): void
    {
        $goal = new WalletGoal();
        $goal->setTargetAmount('5000.00');

        $this->assertSame('5000.00', $goal->getTargetAmount());
    }

    /**
     * Test : L'interface fluent (setters retournent $this).
     */
    public function testSettersAreChainable(): void
    {
        $goal   = new WalletGoal();
        $wallet = new Wallet();

        $result = $goal
            ->setName('Objectif Test')
            ->setTargetAmount('2000.00')
            ->setDeadline(new \DateTimeImmutable('+60 days'))
            ->setWallet($wallet);

        $this->assertSame($goal, $result);
    }
}
