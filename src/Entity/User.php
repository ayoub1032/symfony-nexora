<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uniq_users_email', columns: ['email'])])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3)]
    private ?string $fullName = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resetPinCode = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetPinExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resetPinRequestedAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $faceImage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $googleId = null;

    #[ORM\OneToOne(mappedBy: 'user', targetEntity: Wallet::class)]
    private ?Wallet $wallet = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->roles = ['ROLE_USER'];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = strtolower($email);

        return $this;
    }

    public function getFullName(): ?string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getWallet(): ?Wallet
    {
        return $this->wallet;
    }

    public function setWallet(?Wallet $wallet): self
    {
        if ($wallet !== null && $wallet->getUser() !== $this) {
            $wallet->setUser($this);
        }

        $this->wallet = $wallet;

        return $this;
    }

    public function getResetPinCode(): ?string
    {
        return $this->resetPinCode;
    }

    public function setResetPinCode(?string $resetPinCode): self
    {
        $this->resetPinCode = $resetPinCode;

        return $this;
    }

    public function getResetPinExpiresAt(): ?\DateTimeImmutable
    {
        return $this->resetPinExpiresAt;
    }

    public function setResetPinExpiresAt(?\DateTimeImmutable $resetPinExpiresAt): self
    {
        $this->resetPinExpiresAt = $resetPinExpiresAt;

        return $this;
    }

    public function getResetPinRequestedAt(): ?\DateTimeImmutable
    {
        return $this->resetPinRequestedAt;
    }

    public function setResetPinRequestedAt(?\DateTimeImmutable $resetPinRequestedAt): self
    {
        $this->resetPinRequestedAt = $resetPinRequestedAt;

        return $this;
    }

    public function clearResetPin(): self
    {
        $this->resetPinCode = null;
        $this->resetPinExpiresAt = null;
        $this->resetPinRequestedAt = null;

        return $this;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    public function getFaceImage(): ?string
    {
        return $this->faceImage;
    }

    public function setFaceImage(?string $faceImage): self
    {
        $this->faceImage = $faceImage;

        return $this;
    }

    public function getGoogleId(): ?string
    {
        return $this->googleId;
    }

    public function setGoogleId(?string $googleId): self
    {
        $this->googleId = $googleId;

        return $this;
    }
}
