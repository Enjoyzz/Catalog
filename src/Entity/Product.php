<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Ramsey\Uuid\Uuid;

use function trim;


#[ORM\Entity(repositoryClass: \EnjoysCMS\Module\Catalog\Repository\Product::class)]
#[ORM\Table(name: 'catalog_products')]
class Product
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: 'uuid')]
    private string $id;

    #[ORM\Column(type: 'string')]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'string', length: 64, unique: true, nullable: true, options: ['default' => null])]
    private ?string $productCode = null;

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true, options: ['default' => null])]
    private ?string $sku = null;

    #[ORM\Column(type: 'json', nullable: true, options: ['default' => null])]
    private ?array $barCodes = null;

    #[ORM\ManyToOne(targetEntity: Vendor::class)]
    private ?Vendor $vendor = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true, options: ['default' => null])]
    private ?string $vendorCode = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $hide = false;

    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    private bool $active = true;

    #[ORM\ManyToOne(targetEntity: Category::class)]
    private ?Category $category = null;


    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Image::class)]
    private Collection $images;


    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductFiles::class)]
    private Collection $files;


    #[ORM\OneToOne(mappedBy: 'product', targetEntity: ProductMeta::class, cascade: ['persist', 'remove'])]
    private ?ProductMeta $meta = null;

    #[ORM\OneToOne(mappedBy: 'product', targetEntity: ProductDimensions::class, cascade: ['persist', 'remove'])]
    private ?ProductDimensions $dimensions = null;

    #[ORM\JoinTable(name: 'catalog_products_tags')]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'tag_id', referencedColumnName: 'id')]
    #[ORM\ManyToMany(targetEntity: ProductTag::class)]
    private Collection $tags;

    #[ORM\ManyToMany(targetEntity: OptionValue::class)]
    #[ORM\JoinTable(name: 'catalog_products_options')]
    private Collection $options;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Url::class, cascade: ['persist'])]
    private Collection $urls;

    #[ORM\OneToMany(mappedBy: 'product', targetEntity: ProductPrice::class, cascade: ['persist'])]
    private Collection $prices;

    #[ORM\OneToOne(mappedBy: 'product', targetEntity: Quantity::class, cascade: ['persist'])]
    private ?Quantity $quantity = null;

    #[ORM\Column(name: 'max_discount', type: 'integer', nullable: true)]
    private ?int $maxDiscount = null;

    #[ORM\ManyToOne(targetEntity: ProductUnit::class)]
    private ?ProductUnit $unit = null;

    #[ORM\ManyToOne(targetEntity: ProductGroup::class, cascade: ['persist'], inversedBy: 'products')]
    private ?ProductGroup $group = null;


    #[ORM\OneToMany(mappedBy: 'product', targetEntity: Wishlist::class)]
    private Collection $wishlist;

    public function __construct(string $id = null)
    {
        $this->id = $id ?? Uuid::uuid7()->toString();
        $this->wishlist = new ArrayCollection();
        $this->tags = new ArrayCollection();
        $this->images = new ArrayCollection();
        $this->options = new ArrayCollection();
        $this->urls = new ArrayCollection();
        $this->prices = new ArrayCollection();
        $this->files = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }


    public function getMaxDiscount(): ?int
    {
        return $this->maxDiscount;
    }

    public function setMaxDiscount(?int $maxDiscount): void
    {
        $this->maxDiscount = $maxDiscount;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = trim($name);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getProductCode(): ?string
    {
        return $this->productCode;
    }

    public function setProductCode(?string $productCode): void
    {
        $this->productCode = $productCode;
    }


    public function isHide(): bool
    {
        return $this->hide;
    }

    public function setHide(bool $hide): void
    {
        $this->hide = $hide;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }


    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    /**
     * @throws Exception
     */
    public function getSlug(string $lastPartSlug = null): string
    {
        $category = $this->getCategory();

        $slug = null;
        if ($category instanceof Category) {
            $slug = $category->getSlug() . '/';
        }

        return $slug . ($lastPartSlug ?? $this->getUrl()->getPath());
    }

    /**
     * @return Collection<Image>
     */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function getDefaultImage(): ?Image
    {
        $image = $this->images->filter(fn($i) => $i->isGeneral())->first();
        if (!($image instanceof Image)) {
            return null;
        }
        return $image;
    }

    /**
     * @return Collection<ProductFiles>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }


    /**
     * @return Collection<ProductTag>
     */
    public function getTags(): Collection
    {
        return $this->tags;
    }


    public function addTag(ProductTag $tag): void
    {
        $this->tags->add($tag);
    }

    public function clearTags(): void
    {
        $this->tags = new ArrayCollection();
    }


    public function getMeta(): ?ProductMeta
    {
        return $this->meta;
    }

    /**
     * @param ProductTag[] $tags
     */
    public function addTagsFromArray(array $tags): void
    {
        foreach ($tags as $tag) {
            if (!($tag instanceof ProductTag)) {
                continue;
            }
            $this->addTag($tag);
        }
    }

    /**
     * @return Collection<OptionValue>
     */
    public function getOptionsCollection(): Collection
    {
        return $this->options;
    }


    /**
     * @return (OptionKey|OptionValue[])[][]
     *
     * @psalm-return list<array{key: OptionKey, values?: non-empty-list<OptionValue>}>
     */
    public function getOptions(): array
    {
        $result = [];
        /** @var OptionValue $option */
        foreach ($this->options as $option) {
            /** @var OptionKey $key */
            $key = $option->getOptionKey();
            $result[$key->getId()]['key'] = $key;
            $result[$key->getId()]['values'][] = $option;
        }
        sort($result);
        return $result;
    }

    /**
     * @param int|string|OptionKey $optionKey
     * @return array
     */
    public function getValuesByOptionKey(int|string|OptionKey $optionKey): array
    {
        return array_filter(
            $this->options->toArray(),
            function ($item) use ($optionKey) {
                if ($optionKey instanceof OptionKey) {
                    return $item->getOptionKey()->getId() === $optionKey->getId();
                }
                return $item->getOptionKey()->getId() === $optionKey;
            }
        );
    }

    public function hasOption(int|string|OptionKey $optionKey): bool
    {
        return count(
                array_filter(
                    $this->options->toArray(),
                    function ($item) use ($optionKey) {
                        if ($optionKey instanceof OptionKey) {
                            return $item->getOptionKey()->getId() === $optionKey->getId();
                        }
                        return $item->getOptionKey()->getId() === $optionKey;
                    }
                )
            ) > 0;
    }

    public function clearOptions(): void
    {
        $this->options = new ArrayCollection();
    }

    public function removeOption(OptionValue $optionValue): void
    {
        if ($this->options->contains($optionValue)) {
            $this->options->removeElement($optionValue);
        }
    }

    public function removeOptionByKey(OptionKey $optionKey): void
    {
        /** @var OptionValue $option */
        foreach ($this->options as $option) {
            if ($option->getOptionKey() === $optionKey) {
                $this->options->removeElement($option);
            }
        }
    }

    public function addOption(OptionValue $option): void
    {
        if ($this->options->contains($option)) {
            return;
        }
        $this->options->add($option);
    }

    public function getUrls(): Collection
    {
        return $this->urls;
    }

    private ?Url $currentUrl = null;

    /**
     * @throws Exception
     */
    public function getCurrentUrl(): Url
    {
        return $this->currentUrl ?? $this->getUrl();
    }

    public function setCurrentUrl(Url $currentUrl = null): void
    {
        $this->currentUrl = $currentUrl;
    }

    public function addUrl(Url $url): void
    {
        if ($this->urls->contains($url)) {
            return;
        }
        $this->urls->add($url);
    }

    /**
     * @throws Exception
     */
    public function getUrl(): Url
    {
        $url = $this->getUrls()->filter(function ($item) {
            return $item->isDefault();
        })->current();

        if ($url instanceof Url) {
            return $url;
        }

        $url = new Url();
        $url->setPath(sprintf('Not set urls for product with id: %d', $this->getId()));
        return $url;
//        throw new \Exception(sprintf('Not set urls for product with id: %d', $this->getId()));
    }

    public function getUrlById(int $id): ?Url
    {
        foreach ($this->getUrls() as $url) {
            if ($url->getId() === $id) {
                return $url;
            }
        }
        return null;
    }

    /**
     * @return Collection<ProductPrice>
     */
    public function getPrices(): Collection
    {
        return $this->prices;
    }

    public function getPrice(string $code): ?ProductPrice
    {
        /** @var ProductPrice $price */
        foreach ($this->prices as $price) {
            if ($price->getPriceGroup()->getCode() === $code) {
                return $price;
            }
        }
        return null;
    }

    public function addPrice(ProductPrice $productPrice = null): void
    {
        if ($productPrice === null) {
            return;
        }

        $productPrice->setProduct($this);
        $this->prices->set($productPrice->getPriceGroup()->getCode(), $productPrice);
    }

    public function getQuantity(): Quantity
    {
        if ($this->quantity === null) {
            $this->quantity = new Quantity();
            $this->quantity->setProduct($this);
        }
        return $this->quantity;
    }


    public function setQuantity(Quantity $quantity): void
    {
        $this->quantity = $quantity;
    }


    public function getUnit(): ?ProductUnit
    {
        return $this->unit;
    }

    public function setUnit(?ProductUnit $unit): void
    {
        $this->unit = $unit;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function getBarCodes(): ?array
    {
        return $this->barCodes;
    }

    public function setBarCodes(?array $barCodes): void
    {
        $this->barCodes = $barCodes;
    }

    public function getVendorCode(): ?string
    {
        return $this->vendorCode;
    }

    public function setVendorCode(?string $vendorCode): void
    {
        $this->vendorCode = $vendorCode;
    }

    public function getVendor(): ?Vendor
    {
        return $this->vendor;
    }

    public function setVendor(?Vendor $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function getDimensions(): ?ProductDimensions
    {
        return $this->dimensions;
    }

    public function getGroup(): ?ProductGroup
    {
        return $this->group;
    }

    public function getGroupOptionValue()
    {
        return $this->group->getOptions();
    }

    public function isGrouped(): bool
    {
        return !is_null($this->group);
    }

    public function setGroup(?ProductGroup $group): void
    {
        $this->group = $group;
    }

    public function getWishlist(): Collection
    {
        return $this->wishlist;
    }

    public function isWishlistNotEmpty(): bool
    {
        return !$this->wishlist->isEmpty();
    }

}
