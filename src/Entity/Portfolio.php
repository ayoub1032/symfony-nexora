<?php

namespace App\Entity;

use App\Repository\PortfolioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PortfolioRepository::class)]
class Portfolio
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'User ID is required.')]
    #[Assert\Positive(message: 'User ID must be greater than zero.')]
    private ?int $userId = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Total value is required.')]
    #[Assert\PositiveOrZero(message: 'Total value cannot be negative.')]
    private ?float $totalValue = null;

    #[ORM\OneToMany(mappedBy: 'portfolio', targetEntity: PortfolioAsset::class, cascade: ['persist', 'remove'])]
    private Collection $portfolioAssets;

    public function __construct()
    {
        $this->portfolioAssets = new ArrayCollection();
        $this->totalValue = 0.0;
    }

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

    public function getTotalValue(): ?float
    {
        return $this->totalValue;
    }

    public function setTotalValue(float $totalValue): self
    {
        $this->totalValue = $totalValue;

        return $this;
    }

    /**
     * @return Collection<int, PortfolioAsset>
     */
    public function getPortfolioAssets(): Collection
    {
        return $this->portfolioAssets;
    }

    public function addPortfolioAsset(PortfolioAsset $portfolioAsset): self
    {
        if (!$this->portfolioAssets->contains($portfolioAsset)) {
            $this->portfolioAssets->add($portfolioAsset);
            $portfolioAsset->setPortfolio($this);
        }

        return $this;
    }

    public function removePortfolioAsset(PortfolioAsset $portfolioAsset): self
    {
        if ($this->portfolioAssets->removeElement($portfolioAsset)) {
            // set the owning side to null (unless already changed)
            if ($portfolioAsset->getPortfolio() === $this) {
                $portfolioAsset->setPortfolio(null);
            }
        }

        return $this;
    }
}
