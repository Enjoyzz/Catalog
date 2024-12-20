<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Entity;

use Doctrine\ORM\Mapping as ORM;
use EnjoysCMS\Module\Catalog\Config;

#[ORM\Entity]
#[ORM\Table(name: 'catalog_product_images')]
class Image
{

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string')]
    private string $filename;

    #[ORM\Column(type: 'string')]
    private string $extension;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'image_default'])]
    private string $storage;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $general = false;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'images')]
    private Product $product;

    public function getId(): int
    {
        return $this->id;
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function setProduct(Product $product): void
    {
        $this->product = $product;
    }

    public function getStorage(): string
    {
        return $this->storage;
    }

    public function setStorage(string $storage): void
    {
        $this->storage = $storage;
    }

    public function isGeneral(): bool
    {
        return $this->general;
    }

    public function setGeneral(bool $general): void
    {
        $this->general = $general;
    }

    public function getUrlsStack(Config $config, array $mapName = ['original', 'small', 'large']): array
    {
        $storage = $config->getImageStorageUpload($this->storage);
        $stack = [];
        foreach ($mapName as $suffix) {
            if ($suffix === 'original') {
                $stack[$suffix] = $storage->getUrl($this->filename . '.' . $this->extension);
            }
            $stack[$suffix] = $storage->getUrl(sprintf($this->filename . '_%s.' . $this->extension, $suffix));
        }
        return $stack;
    }

}
