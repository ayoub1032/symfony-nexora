<?php

namespace App\Entity;

use App\Repository\UserReputationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserReputationRepository::class)]
class UserReputation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $userId = null;

    #[ORM\Column]
    private ?int $completedContracts = 0;

    #[ORM\Column]
    private ?int $canceledContracts = 0;

    #[ORM\Column]
    private ?int $totalScore = 0;

    #[ORM\Column]
    private ?int $ratingCount = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;
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
