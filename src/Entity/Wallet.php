<?php

namespace App\Entity;

use App\Repository\WalletRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: WalletRepository::class)]
#[ORM\Table(name: 'wallets')]
class Wallet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Owner name is required.')]
    #[Assert\Length(min: 3, minMessage: 'Owner name must be at least {{ limit }} characters.')]
    private ?string $owner = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Type(type: 'numeric', message: 'Balance must be a number.')]
    #[Assert\PositiveOrZero(message: 'Balance cannot be negative.')]
    private ?string $balance = '0.00';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: WalletGoal::class, orphanRemoval: true)]
    private Collection $walletGoals;

    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: ActivityLog::class, orphanRemoval: true)]
    private Collection $activityLogs;

    #[ORM\OneToMany(mappedBy: 'wallet', targetEntity: Notification::class, orphanRemoval: true)]
    private Collection $notifications;

    public function __construct()
    {
        $this->walletGoals = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getOwner(): ?string { return $this->owner; }
    public function setOwner(string $owner): self { $this->owner = $owner; return $this; }

    public function getBalance(): ?string { return $this->balance; }
    public function setBalance(string $balance): self { $this->balance = $balance; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }

    /**
     * @return Collection<int, ActivityLog>
     */
    public function getActivityLogs(): Collection { return $this->activityLogs; }

    /**
     * Règle Métier : Conversion USDT (1 USDT = 3.10 TND)
     */
    public function getUsdtBalance(): float
    {
        return (float)$this->balance / 3.10;
    }

    /**
     * Règle Métier : Conversion EUR (1 EUR = 3.35 TND)
     */
    public function getEurBalance(): float
    {
        return (float)$this->balance / 3.35;
    }

    /**
     * @return Collection<int, WalletGoal>
     */
    public function getWalletGoals(): Collection { return $this->walletGoals; }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection { return $this->notifications; }
}
