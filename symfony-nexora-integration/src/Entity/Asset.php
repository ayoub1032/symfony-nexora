<?php

namespace App\Entity;

use App\Repository\AssetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AssetRepository::class)]
class Asset
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Asset name is required.')]
    #[Assert\Length(min: 2, minMessage: 'Asset name must be at least {{ limit }} characters.')]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Asset symbol is required.')]
    #[Assert\Length(min: 2, max: 10, minMessage: 'Asset symbol must be at least {{ limit }} characters.', maxMessage: 'Asset symbol cannot exceed {{ limit }} characters.')]
    private ?string $symbol = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Asset value is required.')]
    #[Assert\PositiveOrZero(message: 'Asset value cannot be negative.')]
    private ?float $value = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Asset type is required.')]
    #[Assert\Length(min: 2, minMessage: 'Asset type must be at least {{ limit }} characters.')]
    private ?string $type = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): self
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getValue(): ?float
    {
        return $this->value;
    }

    public function setValue(float $value): self
    {
        $this->value = $value;

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
