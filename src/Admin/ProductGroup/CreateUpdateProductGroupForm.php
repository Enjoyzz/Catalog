<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\ProductGroup;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductGroup;
use EnjoysCMS\Module\Catalog\Entity\ProductGroupOption;
use Psr\Http\Message\ServerRequestInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateUpdateProductGroupForm
{
    public function __construct(
        private readonly EntityManager $em,
        private readonly ServerRequestInterface $request,
        private readonly UrlGeneratorInterface $urlGenerator
    ) {
    }

    /**
     * @throws ExceptionRule
     */
    public function getForm(ProductGroup $productGroup = null): Form
    {
        $form = new Form();
        $form->setDefaults([
            'title' => $productGroup?->getTitle(),
            'options' => array_map(fn($key) => $key->getOptionKey()->getId(),
                $productGroup?->getOptions()->toArray() ?? []),
            'products' => array_map(fn($product) => $product->getId(),
                $productGroup?->getProducts()->toArray() ?? [])
        ]);

        $form->text('title', 'Наименование');


        $form->select('options', 'Параметры')
            ->setDescription(
                $productGroup ? sprintf(
                    '<a href="%s">Расширенная настройка параметров</a>',
                    $this->urlGenerator->generate(
                        '@catalog_product_group_advanced_options',
                        ['group_id' => $productGroup->getId()]
                    )
                ) : null
            )
            ->setMultiple()
            ->fill(function () use ($productGroup) {
                $optionKeys = $productGroup?->getOptions() ?? [];

                $result = [];
                foreach ($optionKeys as $key) {
                    $result[$key->getOptionKey()->getId()] = [
                        $key->getOptionKey()->getName() . (($key->getOptionKey()->getUnit(
                        )) ? ' (' . $key->getOptionKey()->getUnit() . ')' : ''),
                        ['id' => uniqid()]
                    ];
                }
                return $result;
            });


        $form->select('products', 'Товары (продукты)')
            ->setMultiple()
            ->fill(function () use ($productGroup) {
                $products = $productGroup?->getProducts() ?? [];
                $result = [];
                foreach ($products as $product) {
                    $result[$product->getId()] = [
                        sprintf('[SKU: %s] %s', $product->getSku(), $product->getName()),
                        ['id' => uniqid()]
                    ];
                }
                return $result;
            });
        $form->submit('addOrUpdate');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function doAction(ProductGroup $productGroup = null): void
    {
        $relationRepository = $this->em->getRepository(ProductGroupOption::class);


        if ($productGroup === null){
            $productGroup = new ProductGroup();
            $productGroup->setId(Uuid::uuid4());
            $this->em->persist($productGroup);
        }

        $productGroup->setTitle($this->request->getParsedBody()['title'] ?? null);



        $options = $this->em->getRepository(OptionKey::class)->findBy([
            'id' => $this->request->getParsedBody()['options'] ?? []
        ]);

//        $productGroup->removeOptions(
//            array_diff(
//                array_map(fn(ProductGroupOption $relation) => $relation->getOptionKey(),
//                    $productGroup->getOptions()->toArray()),
//                $options
//            )
//        );

        $removeOptions = array_diff(
            array_map(fn(ProductGroupOption $relation) => $relation->getOptionKey(),
                $productGroup->getOptions()->toArray()),
            $options
        );


        foreach ($removeOptions as $removeOption) {
            if (null !== $relation = $relationRepository->find(
                    ['productGroup' => $productGroup, 'optionKey' => $removeOption]
                )) {
                $productGroup->removeOptions([$relation]);
                $this->em->remove($relation);
            }
        }

        foreach ($options as $option) {
            if ($relationRepository->find(['productGroup' => $productGroup, 'optionKey' => $option]) === null) {
                $productGroup->addOption(new ProductGroupOption($productGroup, $option));
            }
        }


        $products = $this->em->getRepository(Product::class)->findBy([
            'id' => $this->request->getParsedBody()['products'] ?? []
        ]);

        $productGroup->removeProducts(
            array_udiff($productGroup->getProducts()->toArray(), $products, function ($a, $b) {
                return $a->getId() <=> $b->getId();
            })
        );
        foreach ($products as $product) {
            $productGroup->addProduct($product);
        }


        $this->em->flush();
    }
}
