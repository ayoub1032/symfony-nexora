<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Note: 'Order' is a reserved keyword in SQL, so we rename the table to `orders`.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`orders`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Please select an asset.')]
    private ?Asset $asset = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'User ID is required.')]
    #[Assert\Positive(message: 'User ID must be greater than zero.')]
    private ?int $userId = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Quantity is required.')]
    #[Assert\Positive(message: 'Quantity must be greater than zero.')]
    private ?int $quantity = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Price is required.')]
    #[Assert\PositiveOrZero(message: 'Price cannot be negative.')]
    private ?float $price = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: 'Order type is required.')]
    #[Assert\Choice(choices: ['BUY', 'SELL'], message: 'Order type must be BUY or SELL.')]
    private ?string $type = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

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

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): self
    {
        $this->price = $price;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;

        return $this;
    }
}
