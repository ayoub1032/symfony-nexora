<?php

namespace App\Entity;

use App\Repository\WalletGoalRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WalletGoalRepository::class)]
#[ORM\Table(name: 'wallet_goals')]
class WalletGoal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Wallet::class, inversedBy: 'walletGoals')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Please select a wallet.')]
    private ?Wallet $wallet = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Goal name is required.')]
    #[Assert\Length(min: 3, minMessage: 'Goal name must be at least {{ limit }} characters.')]
    private ?string $name = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Assert\NotBlank(message: 'Target amount is required.')]
    #[Assert\Positive(message: 'Target amount must be greater than zero.')]
    private ?string $targetAmount = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Deadline is required.')]
    #[Assert\GreaterThanOrEqual('today', message: 'Deadline must be today or in the future.')]
    private ?\DateTimeImmutable $deadline = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['in_progress', 'achieved', 'cancelled'], message: 'Invalid status.')]
    private string $status = 'in_progress';

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getWallet(): ?Wallet { return $this->wallet; }
    public function setWallet(?Wallet $wallet): self { $this->wallet = $wallet; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    public function getTargetAmount(): ?string { return $this->targetAmount; }
    public function setTargetAmount(string $targetAmount): self { $this->targetAmount = $targetAmount; return $this; }

    public function getDeadline(): ?\DateTimeImmutable { return $this->deadline; }
    public function setDeadline(\DateTimeImmutable $deadline): self { $this->deadline = $deadline; return $this; }

    public function getStatus(): string 
    { 
        if ($this->status === 'cancelled') {
            return 'cancelled';
        }

        if ($this->getProgression() >= 100) {
            return 'achieved';
        }

        // Check if deadline has passed
        $today = new \DateTimeImmutable('today');
        if ($this->deadline < $today) {
            return 'expired';
        }

        return $this->status; 
    }
    public function setStatus(string $status): self { $this->status = $status; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    /**
     * Règle Métier 1 : Calcul de progression dynamique
     */
    public function getProgression(): int
    {
        if (!$this->wallet || (float)$this->targetAmount <= 0) {
            return 0;
        }

        $balance = (float)$this->wallet->getBalance();
        $target  = (float)$this->targetAmount;

        $percent = ($balance / $target) * 100;

        return (int)min(100, max(0, $percent));
    }

    /**
     * Règle Métier 3 : Statut d'urgence pour les deadlines proches (< 7 jours)
     */
    public function isUrgent(): bool
    {
        // On n'affiche pas URGENT si l'objectif est atteint (100%), annulé, ou sans deadline
        if (!$this->deadline || $this->status === 'cancelled' || $this->getProgression() >= 100) {
            return false;
        }

        $today = new \DateTimeImmutable('today');
        $diff  = $today->diff($this->deadline);

        return $diff->invert === 0 && $diff->days <= 7;
    }
}
