<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity\Currency;

use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Module\Catalog\Repository\CurrencyRateRepository;


#[ORM\Entity(repositoryClass: CurrencyRateRepository::class)]
#[ORM\Table(name: 'catalog_currency_rate')]
class CurrencyRate
{

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: '`main`')]
    private Currency $currencyMain;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Currency::class)]
    #[ORM\JoinColumn(name: '`convert`')]
    private Currency $currencyConvert;

    #[ORM\Column(type: 'float')]
    private float $rate;

    public function __toString(): string {
        return $this->currencyMain->getCode().$this->currencyConvert->getCode();
    }


    public function getCurrencyMain(): Currency
    {
        return $this->currencyMain;
    }

    public function setCurrencyMain(Currency $currencyMain): void
    {
        $this->currencyMain = $currencyMain;
    }

    public function getCurrencyConvert(): Currency
    {
        return $this->currencyConvert;
    }

    public function setCurrencyConvert(Currency $currencyConvert): void
    {
        $this->currencyConvert = $currencyConvert;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function setRate(float $rate): void
    {
        $this->rate = $rate;
    }
}
