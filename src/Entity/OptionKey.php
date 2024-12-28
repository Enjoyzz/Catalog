<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Module\Catalog\Admin\Product\Options\OptionType;
use EnjoysCMS\Module\Catalog\Repository\OptionKeyRepository;
use JMS\Serializer\Annotation as JMS;


#[ORM\Entity(repositoryClass: OptionKeyRepository::class)]
#[ORM\Table(name: 'catalog_product_option_keys')]
class OptionKey implements \Stringable
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 50)]
    private string $name;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $weight = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $multiple = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $comparable = true;

    #[ORM\Column(type: 'string', options: ['default' => 'ENUM'])]
    private string $type = 'ENUM';

    #[JMS\Type('array')]
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $params = null;

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): void
    {
        $this->note = $note;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): void
    {
        $this->unit = (empty($unit)) ? null : $unit;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function __toString(): string
    {
        return $this->getName() . (($this->getUnit() !== null) ? ', ' . $this->getUnit() : '');
    }

    public function isMultiple(): bool
    {
        return $this->multiple;
    }

    public function setMultiple(bool $multiple): void
    {
        $this->multiple = $multiple;
    }

    /**
     * @throws \ReflectionException
     */
    public function getType(): OptionType
    {
        return OptionType::from($this->type);
    }

    /**
     * @throws \ReflectionException
     */
    public function setType(string|OptionType $optionType): void
    {
        if (is_string($optionType)) {
            $optionType = OptionType::from($optionType);
        }
        $this->type = $optionType->name;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function setParams(?array $params): void
    {
        $this->params = $params;
    }

    public function isComparable(): bool
    {
        return $this->comparable;
    }

    public function setComparable(bool $comparable): void
    {
        $this->comparable = $comparable;
    }
}
