<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Helpers;


use Doctrine\ORM\EntityManager;
use EnjoysCMS\Core\Components\Helpers\HelpersBase;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Repository\OptionKeyRepository;

final class ProductOptions
{

    public static function getOptionKey(string $name, string $unit = null): ?OptionKey
    {
        /** @var OptionKeyRepository $repository */
        $repository = self::$container->get(EntityManager::class)->getRepository(OptionKey::class);
        return $repository->findOneBy(
            [
                'name' => $name,
                'unit' => $unit
            ]
        );
    }
}
