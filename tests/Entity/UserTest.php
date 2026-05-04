<?php

namespace App\Tests\Entity;

use App\Entity\User;
use App\Entity\Wallet;
use App\Entity\UserReputation;
use PHPUnit\Framework\TestCase;

/**
 * =============================================================================
 *  TESTS UNITAIRES — Entité User
 * =============================================================================
 *
 *  User est l'entité centrale du projet. Elle implémente 2 interfaces Symfony :
 *    - UserInterface (sécurité : roles, identifiant)
 *    - PasswordAuthenticatedUserInterface (authentification par mot de passe)
 *
 *  RÈGLES MÉTIER À TESTER :
 *
 *  RÈGLE 1 — setEmail() normalise en minuscules :
 *    "Alice@NEXORA.IO" → "alice@nexora.io"
 *
 *  RÈGLE 2 — getRoles() toujours ajoute ROLE_USER :
 *    Même si $roles est vide ou n'a que ['ROLE_ADMIN'], ROLE_USER est toujours présent.
 *    Le tableau est dédupliqué (pas de doublons).
 *
 *  RÈGLE 3 — hasRole() vérifie si un rôle est présent.
 *
 *  RÈGLE 4 — getUserIdentifier() retourne l'email (identifiant Symfony Security).
 *
 *  RÈGLE 5 — Associations bidirectionnelles avec Wallet, Portfolio, UserReputation.
 * =============================================================================
 */
class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    // =========================================================================
    //  Test 1 : Valeurs par défaut du constructeur
    // =========================================================================

    /**
     * Test : Le constructeur définit createdAt, roles=['ROLE_USER'] et riskProfile='Balanced'.
     */
    public function testConstructorDefaults(): void
    {
        // createdAt défini automatiquement
        $this->assertNotNull($this->user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->user->getCreatedAt());

        // ROLE_USER par défaut
        $this->assertContains('ROLE_USER', $this->user->getRoles());

        // Profil de risque par défaut
        $this->assertSame('Balanced', $this->user->getRiskProfile());
    }

    // =========================================================================
    //  Test 2 : Règle setEmail() — normalisation en minuscules
    // =========================================================================

    /**
     * Test : setEmail() convertit l'email en minuscules.
     * Cela empêche les doublons de compte (Alice@test.com ≠ alice@test.com).
     */
    public function testSetEmailNormalizesToLowercase(): void
    {
        $this->user->setEmail('Alice@NEXORA.IO');

        $this->assertSame('alice@nexora.io', $this->user->getEmail());
    }

    /**
     * Test : setEmail() avec un email déjà en minuscules ne change rien.
     */
    public function testSetEmailPreservesLowercaseEmail(): void
    {
        $this->user->setEmail('bob@nexora.io');

        $this->assertSame('bob@nexora.io', $this->user->getEmail());
    }

    /**
     * Test : setEmail() avec un email mixte.
     */
    public function testSetEmailWithMixedCase(): void
    {
        $this->user->setEmail('TrAdEr42@ExAmPle.COM');

        $this->assertSame('trader42@example.com', $this->user->getEmail());
    }

    // =========================================================================
    //  Test 3 : Règle getRoles() — ROLE_USER toujours présent + dédupliqué
    // =========================================================================

    /**
     * Test : getRoles() retourne toujours ROLE_USER, même si aucun rôle n'a été
     * défini manuellement.
     */
    public function testGetRolesAlwaysContainsRoleUser(): void
    {
        // L'utilisateur n'a aucun rôle ajouté manuellement
        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_USER', $roles);
    }

    /**
     * Test : getRoles() avec ROLE_ADMIN → retourne [ROLE_ADMIN, ROLE_USER] sans doublons.
     */
    public function testGetRolesWithAdminRole(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);

        $roles = $this->user->getRoles();

        $this->assertContains('ROLE_ADMIN', $roles);
        $this->assertContains('ROLE_USER',  $roles);

        // Pas de doublon : ROLE_USER ne doit apparaître qu'une fois
        $this->assertSame(
            count($roles),
            count(array_unique($roles)),
            'getRoles() ne doit pas contenir de doublons'
        );
    }

    /**
     * Test : Si on ajoute ROLE_USER explicitement, il ne doit pas apparaître en double.
     */
    public function testGetRolesDeduplicatesRoleUser(): void
    {
        // On passe ROLE_USER en double
        $this->user->setRoles(['ROLE_USER', 'ROLE_USER']);

        $roles = $this->user->getRoles();

        // Compter les occurrences de ROLE_USER → doit être 1
        $count = count(array_filter($roles, fn($r) => $r === 'ROLE_USER'));
        $this->assertSame(1, $count, 'ROLE_USER ne doit apparaître qu\'une seule fois');
    }

    // =========================================================================
    //  Test 4 : getUserIdentifier() — identifiant Symfony Security
    // =========================================================================

    /**
     * Test : getUserIdentifier() retourne l'email de l'utilisateur.
     * Symfony Security utilise cet identifiant pour l'authentification.
     */
    public function testGetUserIdentifierReturnsEmail(): void
    {
        $this->user->setEmail('nexora@test.com');

        $this->assertSame('nexora@test.com', $this->user->getUserIdentifier());
    }

    /**
     * Test : getUserIdentifier() retourne '' si l'email n'est pas encore défini.
     */
    public function testGetUserIdentifierReturnsEmptyStringWithNoEmail(): void
    {
        // Pas de setEmail() appelé → email = null
        $this->assertSame('', $this->user->getUserIdentifier());
    }

    // =========================================================================
    //  Test 5 : hasRole() — vérification d'un rôle
    // =========================================================================

    /**
     * Test : hasRole('ROLE_USER') retourne toujours true.
     */
    public function testHasRoleUserAlwaysTrue(): void
    {
        $this->assertTrue($this->user->hasRole('ROLE_USER'));
    }

    /**
     * Test : hasRole('ROLE_ADMIN') retourne false si l'utilisateur est simple.
     */
    public function testHasRoleAdminReturnsFalseForNormalUser(): void
    {
        $this->assertFalse($this->user->hasRole('ROLE_ADMIN'));
    }

    /**
     * Test : hasRole('ROLE_ADMIN') retourne true après setRoles(['ROLE_ADMIN']).
     */
    public function testHasRoleAdminReturnsTrueWhenSet(): void
    {
        $this->user->setRoles(['ROLE_ADMIN']);

        $this->assertTrue($this->user->hasRole('ROLE_ADMIN'));
    }

    // =========================================================================
    //  Test 6 : Getters / Setters de base
    // =========================================================================

    /**
     * Test : setFullName() et getFullName().
     */
    public function testSetAndGetFullName(): void
    {
        $this->user->setFullName('Jean Dupont');
        $this->assertSame('Jean Dupont', $this->user->getFullName());
    }

    /**
     * Test : setPassword() et getPassword().
     */
    public function testSetAndGetPassword(): void
    {
        $hashedPassword = '$2y$13$someHashedPasswordHere';
        $this->user->setPassword($hashedPassword);

        $this->assertSame($hashedPassword, $this->user->getPassword());
    }

    /**
     * Test : setRiskProfile() et getRiskProfile().
     */
    public function testSetAndGetRiskProfile(): void
    {
        $this->user->setRiskProfile('Aggressive');
        $this->assertSame('Aggressive', $this->user->getRiskProfile());

        $this->user->setRiskProfile('Conservative');
        $this->assertSame('Conservative', $this->user->getRiskProfile());
    }

    /**
     * Test : eraseCredentials() n'a pas d'effet visible (méthode vide requise par l'interface).
     * On vérifie juste qu'elle ne lève pas d'exception.
     */
    public function testEraseCredentialsDoesNotThrow(): void
    {
        $this->user->setPassword('some_password');
        $this->user->eraseCredentials();

        // La méthode est vide, pas d'assertion possible, mais ne doit pas crasher
        $this->addToAssertionCount(1); // marque le test comme ayant fait une vérification
    }

    // =========================================================================
    //  Test 7 : Association bidirectionnelle avec Wallet
    // =========================================================================

    /**
     * Test : setWallet() crée l'association et synchronise user→wallet.
     * La logique dans setWallet() appelle $wallet->setUser($this) si nécessaire.
     */
    public function testSetWalletCreatesAssociation(): void
    {
        $wallet = new Wallet();
        $wallet->setOwner('Test Owner')->setBalance('1000.00');

        $this->user->setWallet($wallet);

        $this->assertSame($wallet, $this->user->getWallet());
        // Le wallet doit aussi référencer le user (synchronisation bidirectionnelle)
        $this->assertSame($this->user, $wallet->getUser());
    }

    // =========================================================================
    //  Test 8 : Association avec UserReputation
    // =========================================================================

    /**
     * Test : Initialement, l'utilisateur n'a pas de réputation.
     */
    public function testUserHasNoReputationByDefault(): void
    {
        $this->assertNull($this->user->getReputation());
    }

    /**
     * Test : setReputation() crée l'association et synchronise la relation inverse.
     */
    public function testSetReputationSyncsRelation(): void
    {
        $rep = new UserReputation();
        $rep->setCompletedContracts(5);

        $this->user->setReputation($rep);

        $this->assertSame($rep, $this->user->getReputation());
        // La réputation doit référencer le user (synchronisation)
        $this->assertSame($this->user, $rep->getUser());
    }

    // =========================================================================
    //  Test 9 : Scénario complet
    // =========================================================================

    /**
     * Test : Création d'un utilisateur complet (comme le ferait un controller de registration).
     */
    public function testCompleteUserCreationScenario(): void
    {
        $this->user
            ->setEmail('NewUser@NEXORA.IO')
            ->setFullName('New User')
            ->setPassword('$2y$13$hashed')
            ->setRoles(['ROLE_USER'])
            ->setRiskProfile('Conservative');

        // Email normalisé
        $this->assertSame('newuser@nexora.io', $this->user->getEmail());
        $this->assertSame('New User',          $this->user->getFullName());
        $this->assertSame('Conservative',      $this->user->getRiskProfile());

        // ROLE_USER présent, pas de doublon
        $roles = $this->user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertSame(count($roles), count(array_unique($roles)));

        // getUserIdentifier retourne l'email normalisé
        $this->assertSame('newuser@nexora.io', $this->user->getUserIdentifier());
    }
}
