<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;


use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use InvalidArgumentException;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_product_quantity')]
final class Quantity
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private int $id;


    #[ORM\OneToOne(inversedBy: 'quantity', targetEntity: Product::class)]
    private Product $product;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $qty = 0;

    #[ORM\Column(type: 'float', options: ['default' => 1])]
    private float $step = 1;

    #[ORM\Column(type: 'float', options: ['default' => 1])]
    private float $min = 1;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private float $reserve = 0;


    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $updated = false;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    #[Gedmo\Timestampable(on: 'update')]
    private ?DateTimeInterface $updatedAt;


    #[ORM\Column(name: 'arrival_date', type: 'datetime', nullable: true)]
    private DateTimeInterface $arrivalDate;


    public function isUpdated(): bool
    {
        return $this->updated;
    }

    public function setUpdated(bool $updated): void
    {
        $this->updated = $updated;
    }


    public function __construct()
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function __toString(): string
    {
        return (string)$this->getRealQty();
    }

    public function getReserve(): float|int
    {
        return $this->reserve;
    }


    public function setReserve(float|int $reserve): self
    {
        $this->reserve = $reserve;
        return $this;
    }


    public function getQty(): float|int
    {
        return $this->qty;
    }


    public function setQty(float|int|string $qty): self
    {
        if (!is_numeric($qty)) {
            throw new InvalidArgumentException();
        }
        $this->qty = (float)$qty;
        return $this;
    }


    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }


    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getArrivalDate(): DateTimeInterface
    {
        return $this->arrivalDate;
    }

    public function setArrivalDate(DateTimeInterface $arrivalDate): void
    {
        $this->arrivalDate = $arrivalDate;
    }

    public function getRealQty(): float|int
    {
        return ($this->getQty() - $this->getReserve());
    }


    public function getStep(): float
    {
        return $this->step;
    }


    public function setStep(float|int|string $step): void
    {
        if (!is_numeric($step)) {
            throw new InvalidArgumentException();
        }
        $this->step = (float)$step;
    }


    public function getMin(): float
    {
        return $this->min;
    }


    public function setMin(float|int|string $min): void
    {
        if (!is_numeric($min)) {
            throw new InvalidArgumentException();
        }
        $this->min = (float)$min;
    }


}
