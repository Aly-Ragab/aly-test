<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'coffee_beans')]
class CoffeeBean
{
    #[ORM\Id]
    #[ORM\Column(type: 'string')]
    private string $sku;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'integer')]
    private int $inStock;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $origin = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $roast = null;

    // Getters and Setters...
    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = $sku;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getInStock(): int
    {
        return $this->inStock;
    }

    public function setInStock(int $inStock): self
    {
        $this->inStock = $inStock;

        return $this;
    }

    public function getOrigin(): ?array
    {
        return $this->origin;
    }

    public function setOrigin(?array $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getRoast(): ?array
    {
        return $this->roast;
    }

    public function setRoast(?array $roast): self
    {
        $this->roast = $roast;

        return $this;
    }

}
