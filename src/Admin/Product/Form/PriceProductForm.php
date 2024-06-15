<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Product\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\Currency\Currency;
use EnjoysCMS\Module\Catalog\Entity\PriceGroup;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductPrice;
use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;

final class PriceProductForm
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request
    ) {
    }


    public function getForm(Product $product): Form
    {
        $priceGroups = $this->em->getRepository(PriceGroup::class)->findAll();
        $priceDefaults = [];

        foreach ($priceGroups as $priceGroup) {
            $priceDefaults[$priceGroup->getCode()] = $product->getPrice($priceGroup->getCode())?->getPrice() ?? 0;
        }

        $form = new Form();
        $form->setDefaults([
            'price' => $priceDefaults,
            'currency' => $product->getPrices()->get(0)?->getCurrency()->getId(),
        ]);

        $form->select('currency', 'Валюта')->fill(function () {
            $ret = [];
            foreach ($this->em->getRepository(Currency::class)->findAll() as $item) {
                $ret[$item->getId()] = $item->getName();
            }
            return $ret;
        });

        $form->header(sprintf('Единица измерения: %s', $product->getUnit()?->getName() ?? '-'));

        foreach ($priceGroups as $priceGroup) {
            $form->number(sprintf('price[%s]', $priceGroup->getCode()), $priceGroup->getTitle())
                ->setAttribute(AttributeFactory::create('step', '0.01'))
                ->setDescription($priceGroup->getCode());
        }
        $form->submit('set', 'Установить');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function doAction(Product $product): void
    {
        $priceGroups = $this->em->getRepository(PriceGroup::class)->findAll();
        $currency = $this->em->getRepository(Currency::class)->find(
            $this->request->getParsedBody()['currency'] ?? null
        );

        if ($currency === null) {
            throw new InvalidArgumentException('Currency not found');
        }


        foreach ($priceGroups as $priceGroup) {
            foreach (($this->request->getParsedBody()['price'] ?? []) as $code => $price) {
                if ($priceGroup->getCode() !== $code) {
                    continue;
                }

                if (!is_numeric($price)) {
                    continue;
                }


                $priceEntity = $product->getPrices()->findFirst(
                    fn($key, $el) => $code === $el->getPriceGroup()->getCode()
                );

                if ($priceEntity === null) {
                    if ($price == 0) {
                        continue;
                    }

                    $priceEntity = new ProductPrice();
                    $priceEntity->setPrice($price);
                    $priceEntity->setProduct($product);
                    $priceEntity->setPriceGroup($priceGroup);
                    $priceEntity->setCurrency($currency);
                    $this->em->persist($priceEntity);
                    continue;
                }


                if ($price == 0) {
                    $this->em->remove($priceEntity);
                    continue;
                }

                $priceEntity->setPrice($price);
                $priceEntity->setCurrency($currency);
            }
        }

        $this->em->flush();
    }
}
