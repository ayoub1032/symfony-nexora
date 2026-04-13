<?php

namespace App\Entity;

use App\Repository\P2pContractRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: P2pContractRepository::class)]
class P2pContract
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Creator ID is required.')]
    #[Assert\Positive(message: 'Creator ID must be greater than zero.')]
    private ?int $creatorId = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Please select an asset.')]
    private ?Asset $asset = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Quantity is required.')]
    #[Assert\Positive(message: 'Quantity must be greater than zero.')]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Price per unit is required.')]
    #[Assert\PositiveOrZero(message: 'Price per unit cannot be negative.')]
    private ?float $pricePerUnit = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Contract type is required.')]
    #[Assert\Choice(choices: ['BUY', 'SELL'], message: 'Contract type must be BUY or SELL.')]
    private ?string $contractType = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Status is required.')]
    #[Assert\Choice(choices: ['OPEN', 'ACCEPTED', 'COMPLETED', 'CANCELLED'], message: 'Invalid contract status.')]
    private ?string $status = null;

    #[ORM\Column(nullable: true)]
    private ?int $acceptedBy = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = 'OPEN';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatorId(): ?int
    {
        return $this->creatorId;
    }

    public function setCreatorId(int $creatorId): self
    {
        $this->creatorId = $creatorId;
        return $this;
    }

    public function getAsset(): ?Asset
    {
        return $this->asset;
    }

    public function setAsset(?Asset $asset): self
    {
        $this->asset = $asset;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function getPricePerUnit(): ?float
    {
        return $this->pricePerUnit;
    }

    public function setPricePerUnit(float $pricePerUnit): self
    {
        $this->pricePerUnit = $pricePerUnit;
        return $this;
    }

    public function getContractType(): ?string
    {
        return $this->contractType;
    }

    public function setContractType(string $contractType): self
    {
        $this->contractType = $contractType;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getAcceptedBy(): ?int
    {
        return $this->acceptedBy;
    }

    public function setAcceptedBy(?int $acceptedBy): self
    {
        $this->acceptedBy = $acceptedBy;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeInterface
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeInterface $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;
        return $this;
    }
}
