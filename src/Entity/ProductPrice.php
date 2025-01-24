<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Module\Catalog\Entity\Currency\Currency;
use EnjoysCMS\Module\Catalog\Helpers\Normalize;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity]
#[ORM\EntityListeners([ProductPriceEntityListener::class])]
#[ORM\Table(name: 'catalog_product_prices')]
final class ProductPrice
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private int $id;


    #[ORM\Column(type: 'integer', length: 11)]
    private int $price;

    #[ORM\Column(name: 'updated_at', type: 'datetime')]
    #[Gedmo\Timestampable(on: 'update')]
    private DateTime $updatedAt;


    #[ORM\ManyToOne(targetEntity: PriceGroup::class)]
    private PriceGroup $priceGroup;


    #[ORM\ManyToOne(targetEntity: Product::class, cascade: ['persist'], inversedBy: 'prices')]
    private Product $product;

    #[ORM\ManyToOne(targetEntity: Currency::class)]
    private Currency $currency;

    private ?Currency $currentCurrency = null;


    public function __construct()
    {
        $this->updatedAt = new DateTime();
    }

    /**
     * @throws \Exception
     */
    public function __toString(): string
    {
        return $this->format();
    }

    /**
     * @throws \Exception
     */
    public function format(): string
    {
        return  $this->getCurrentCurrency()->format($this->getPrice());
    }

    /**
     * @deprecated
     */
    public function getFormatted(
        $number,
        string $decimal_separator = '.',
        string $thousands_separator = ' ',
        string $nbsp = '&nbsp;'
    ): string {
        return str_replace(
            ' ',
            $nbsp,
            $this->getCurrency()->getLeft() .
            number_format(
                $number,
                $this->getCurrency()->getPrecision(),
                $decimal_separator,
                $thousands_separator
            ) .
            $this->getCurrency()->getRight()
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getPrice(): int|float
    {
        return $this->price / 100;
    }

    /**
     * @param numeric|numeric-string $price
     */
    public function setPrice($price): void
    {
        $this->price = Normalize::toPrice($price);
    }


    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }


    public function getPriceGroup(): PriceGroup
    {
        return $this->priceGroup;
    }


    public function setPriceGroup(PriceGroup $priceGroup): void
    {
        $this->priceGroup = $priceGroup;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function setCurrency(Currency $currency): void
    {
        $this->currency = $currency;
    }

    /**
     * @throws \Exception
     * @return array{rawPrice: float|int, formattedPrice: string}
     */
    public function getComputedPriceBasedQuantity(int|float $count): array
    {
        $rawPrice = $this->getPrice() * $count;
        return [
            'rawPrice' => $rawPrice,
            'formattedPrice' => $this->getCurrentCurrency()->format($rawPrice)
        ];
    }


    public function getCurrentCurrency(): Currency
    {
        return $this->currentCurrency ?? $this->getCurrency();
    }

    public function setCurrentCurrency(?Currency $currentCurrency): void
    {
        $this->currentCurrency = $currentCurrency;
    }


}
