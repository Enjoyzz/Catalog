<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Module\Catalog\Admin\Product\Options\OptionType;
use EnjoysCMS\Module\Catalog\Repository\OptionValueRepository;
use Symfony\Component\Serializer\Annotation\Ignore;

#[ORM\Table(name: 'catalog_product_option_values')]
#[ORM\Entity(repositoryClass: OptionValueRepository::class)]
class OptionValue
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $value;

    #[ORM\ManyToOne(targetEntity: OptionKey::class)]
    #[ORM\JoinColumn(name: 'key_id', referencedColumnName: 'id')]
    private OptionKey $optionKey;

    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'options')]
    private Collection $products;

    public function __construct()
    {
        $this->products = new ArrayCollection();
    }

    /**
     * @throws \ReflectionException
     */
    public function __toString(): string
    {
        return $this->getValue();
    }

    /**
     * @throws \ReflectionException
     */
    public function getValue(): string
    {
        return match ($this->optionKey->getType()) {
            OptionType::BOOL => ($this->value === '0')
                ? $this->optionKey->getParams()[0] ?? 'Нет'
                : $this->optionKey->getParams()[1] ?? 'Да',
            default => $this->value
        };
    }

    public function getRawValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getOptionKey(): OptionKey
    {
        return $this->optionKey;
    }

    public function setOptionKey(mixed $optionKey): void
    {
        $this->optionKey = $optionKey;
    }

    public function getProducts(): Collection
    {
        return $this->products;
    }

}
