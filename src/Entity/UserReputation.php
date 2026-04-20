<?php

namespace App\Entity;

use App\Repository\UserReputationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserReputationRepository::class)]
class UserReputation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'reputation', targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User is required.')]
    private ?User $user = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Completed contracts is required.')]
    #[Assert\PositiveOrZero(message: 'Completed contracts cannot be negative.')]
    private ?int $completedContracts = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Canceled contracts is required.')]
    #[Assert\PositiveOrZero(message: 'Canceled contracts cannot be negative.')]
    private ?int $canceledContracts = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Total score is required.')]
    #[Assert\PositiveOrZero(message: 'Total score cannot be negative.')]
    private ?int $totalScore = 0;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Rating count is required.')]
    #[Assert\PositiveOrZero(message: 'Rating count cannot be negative.')]
    private ?int $ratingCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getCompletedContracts(): ?int
    {
        return $this->completedContracts;
    }

    public function setCompletedContracts(int $completedContracts): self
    {
        $this->completedContracts = $completedContracts;
        return $this;
    }

    public function getCanceledContracts(): ?int
    {
        return $this->canceledContracts;
    }

    public function setCanceledContracts(int $canceledContracts): self
    {
        $this->canceledContracts = $canceledContracts;
        return $this;
    }

    public function getTotalScore(): ?int
    {
        return $this->totalScore;
    }

    public function setTotalScore(int $totalScore): self
    {
        $this->totalScore = $totalScore;
        return $this;
    }

    public function getRatingCount(): ?int
    {
        return $this->ratingCount;
    }

    public function setRatingCount(int $ratingCount): self
    {
        $this->ratingCount = $ratingCount;
        return $this;
    }

    public function getAverageRating(): float
    {
        if ($this->ratingCount === 0) {
            return 0.0;
        }
        return (float) $this->totalScore / $this->ratingCount;
    }
}
