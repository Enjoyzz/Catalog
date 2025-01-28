<?php

namespace EnjoysCMS\Module\Catalog\Models;

use Doctrine\ORM\Exception\NotSupported;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Repository\OptionKeyRepository;

final class PrepareProductModel
{
    /**
     * @throws NotSupported
     */
    public function __construct(
        private readonly Product $product,
        OptionKeyRepository $optionKeyRepository,
        Config $config,
    ) {
        $globalExtraFields = array_filter(
            array_map(static function ($item) use ($optionKeyRepository, $config) {
                return $optionKeyRepository->find($item);
            }, explode(',', $config->getGlobalExtraFields()))
        );

        foreach ($globalExtraFields as $globalExtraField) {
            $this->product->getCategory()->addExtraField($globalExtraField);
        }
    }

    public function getProduct(): Product
    {
        return $this->product;
    }
}
