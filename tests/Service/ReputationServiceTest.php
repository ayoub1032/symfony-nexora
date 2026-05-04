<?php

namespace App\Tests\Service;

use App\Entity\User;
use App\Entity\UserReputation;
use App\Service\ReputationService;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — ReputationService
 * =============================================================================
 *
 *  ReputationService n'a AUCUNE dépendance externe (pas de BDD, pas d'HTTP).
 *  C'est le cas le plus simple à tester : on crée juste les entités et on
 *  vérifie la logique métier.
 *
 *  LOGIQUE MÉTIER TESTÉE :
 *    - Utilisateur sans réputation → valeurs par défaut
 *    - Calcul du nombre d'étoiles (0–5) selon taux de complétion
 *    - Attribution du rang selon le nombre de contrats complétés :
 *        •  0 complété         → Rookie
 *        •  1–4 complétés      → Active Member
 *        •  5–19 complétés     → Verified Pro
 *        • 20–49 complétés     → Expert Trader
 *        • 50+ complétés       → Grand Master
 * =============================================================================
 */
class ReputationServiceTest extends TestCase
{
    private ReputationService $service;

    protected function setUp(): void
    {
        // Aucune dépendance → instanciation directe
        $this->service = new ReputationService();
    }

    // =========================================================================
    //  Helpers privés pour construire des entités de test
    // =========================================================================

    /**
     * Crée un objet User minimal (sans BDD).
     * On utilise un objet User réel (pas un mock) car User est une simple entité.
     */
    private function makeUser(): User
    {
        return new User();
    }

    /**
     * Crée un objet UserReputation avec des valeurs données.
     */
    private function makeReputation(int $completed, int $canceled): UserReputation
    {
        $rep = new UserReputation();
        $rep->setCompletedContracts($completed);
        $rep->setCanceledContracts($canceled);
        return $rep;
    }

    // =========================================================================
    //  Test 1 : Utilisateur sans réputation (objet null)
    // =========================================================================

    /**
     * Test : Si getReputation() retourne null (nouvel utilisateur),
     * le service doit retourner des valeurs par défaut sûres.
     */
    public function testGetReputationStatsWithNoReputation(): void
    {
        // Arrange : User sans réputation associée
        $user = $this->makeUser();
        // Par défaut, User::getReputation() retourne null

        // Act
        $stats = $this->service->getReputationStats($user);

        // Assert : structure complète et valeurs par défaut
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('score',     $stats);
        $this->assertArrayHasKey('stars',     $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('canceled',  $stats);
        $this->assertArrayHasKey('rank',      $stats);
        $this->assertArrayHasKey('color',     $stats);

        $this->assertSame(0,           $stats['score']);
        $this->assertSame(0,           $stats['stars']);
        $this->assertSame(0,           $stats['completed']);
        $this->assertSame(0,           $stats['canceled']);
        $this->assertSame('Newcomer',  $stats['rank']);
        $this->assertSame('#94a3b8',   $stats['color']);
    }

    // =========================================================================
    //  Test 2 : Calcul des étoiles
    // =========================================================================

    /**
     * Test : 0 contrat complété + 0 annulé → 0 étoiles (pas de division par zéro).
     */
    public function testStarsAreZeroWithNoContracts(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(0, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame(0.0, (float)$stats['stars']);
    }

    /**
     * Test : 10 complétés, 0 annulés → taux = 100 % → 5 étoiles.
     */
    public function testStarsAreFiveWithPerfectRecord(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(10, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame(5.0, (float)$stats['stars']);
    }

    /**
     * Test : 5 complétés, 5 annulés → taux = 50 % → 2.5 étoiles.
     */
    public function testStarsAreHalfWithFiftyPercent(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(5, 5);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame(2.5, (float)$stats['stars']);
    }

    // =========================================================================
    //  Test 3 : Attribution des rangs
    // =========================================================================

    /**
     * Test : 0 complété (et aucun annulé) → rang "Rookie".
     */
    public function testRankIsRookieWithZeroCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(0, 3);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Rookie', $stats['rank']);
        $this->assertSame('#94a3b8', $stats['color']);
    }

    /**
     * Test : 1 complété → rang "Active Member" (couleur verte).
     */
    public function testRankIsActiveMemberWithOneCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(1, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Active Member', $stats['rank']);
        $this->assertSame('#10b981',       $stats['color']);
    }

    /**
     * Test : 4 complétés → toujours "Active Member".
     */
    public function testRankIsActiveMemberWithFourCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(4, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Active Member', $stats['rank']);
    }

    /**
     * Test : 5 complétés → rang "Verified Pro" (couleur indigo).
     */
    public function testRankIsVerifiedProWithFiveCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(5, 2);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Verified Pro', $stats['rank']);
        $this->assertSame('#6366f1',      $stats['color']);
    }

    /**
     * Test : 19 complétés → "Verified Pro" (juste sous Expert Trader).
     */
    public function testRankIsVerifiedProWithNineteenCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(19, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Verified Pro', $stats['rank']);
    }

    /**
     * Test : 20 complétés → rang "Expert Trader" (couleur violette).
     */
    public function testRankIsExpertTraderWithTwentyCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(20, 5);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Expert Trader', $stats['rank']);
        $this->assertSame('#a855f7',       $stats['color']);
    }

    /**
     * Test : 50 complétés → rang "Grand Master" (couleur ambre/or).
     */
    public function testRankIsGrandMasterWithFiftyCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(50, 10);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Grand Master', $stats['rank']);
        $this->assertSame('#f59e0b',      $stats['color']);
    }

    /**
     * Test : 100 complétés → toujours "Grand Master".
     */
    public function testRankIsGrandMasterWithHundredCompleted(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(100, 0);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame('Grand Master', $stats['rank']);
    }

    // =========================================================================
    //  Test 4 : Cohérence du score
    // =========================================================================

    /**
     * Test : le champ 'score' est égal au nombre de contrats complétés.
     */
    public function testScoreEqualsCompletedContracts(): void
    {
        $user = $this->makeUser();
        $rep  = $this->makeReputation(42, 8);
        $user->setReputation($rep);

        $stats = $this->service->getReputationStats($user);

        $this->assertSame(42, $stats['score']);
        $this->assertSame(42, $stats['completed']);
        $this->assertSame(8,  $stats['canceled']);
    }
}
