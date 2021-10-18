<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Models\Admin\Product\Urls;

use App\Module\Admin\Core\ModelInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ObjectRepository;
use Enjoys\Http\ServerRequestInterface;
use EnjoysCMS\Module\Catalog\Entities\OptionKey;
use EnjoysCMS\Module\Catalog\Entities\OptionValue;
use EnjoysCMS\Module\Catalog\Entities\Product;
use EnjoysCMS\Module\Catalog\Repositories\OptionKeyRepository;
use EnjoysCMS\Module\Catalog\Repositories\OptionValueRepository;
use EnjoysCMS\Module\Catalog\Repositories\Product as ProductRepository;

final class Manage  implements ModelInterface
{
    private ObjectRepository|EntityRepository|ProductRepository $productRepository;
    protected Product $product;
    private ObjectRepository|EntityRepository|OptionKeyRepository $keyRepository;
    private ObjectRepository|EntityRepository|OptionValueRepository $valueRepository;

    /**
     * @throws NoResultException
     */
    public function __construct(
        private EntityManager $em,
        private ServerRequestInterface $serverRequest
    ) {
        $this->keyRepository = $this->em->getRepository(OptionKey::class);
        $this->valueRepository = $this->em->getRepository(OptionValue::class);
        $this->productRepository = $this->em->getRepository(Product::class);
        $this->product = $this->getProduct();
    }


    /**
     * @throws NoResultException
     */
    private function getProduct(): Product
    {
        $product = $this->productRepository->find($this->serverRequest->get('id'));
        if ($product === null) {
            throw new NoResultException();
        }
        return $product;
    }

    public function getContext(): array
    {
        return [
            'product' => $this->product,
            'subtitle' => 'URLs'
        ];
    }
}