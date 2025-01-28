<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Models;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use EnjoysCMS\Core\Breadcrumbs\BreadcrumbCollection;
use EnjoysCMS\Core\Http\Response\RedirectInterface;
use EnjoysCMS\Core\Setting\Setting;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\OptionValue;
use EnjoysCMS\Module\Catalog\Entity\PriceGroup;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductPriceEntityListener;
use EnjoysCMS\Module\Catalog\Repository;
use EnjoysCMS\Module\Catalog\Service\MetaGenerator;
use Exception;
use Invoker\InvokerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ProductModel implements ModelInterface
{

    private Product $product;

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws NotSupported
     */
    public function __construct(
        private readonly InvokerInterface $invoker,
        private readonly EntityManager $em,
        private readonly Repository\OptionKeyRepository $optionKeyRepository,
        private readonly Repository\Product $productRepository,
        private readonly ServerRequestInterface $request,
        private readonly BreadcrumbCollection $breadcrumbs,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RedirectInterface $redirect,
        private readonly Setting $setting,
        private readonly Config $config,
        private readonly MetaGenerator $metaGenerator
    ) {
        $entityListenerResolver = $this->em->getConfiguration()->getEntityListenerResolver();
        $entityListenerResolver->register(new ProductPriceEntityListener($this->config));

        $this->product = $this->getProduct();
    }

    /**
     * @throws Exception
     */
    public function getContext(): array
    {
        if ($this->product->getUrl() !== $this->product->getCurrentUrl()) {
            $this->redirect->toRoute(
                'catalog/product',
                ['slug' => $this->product->getSlug()],
                301,
                true
            );
        }
        // $this->em->flush();
        //dd($this->product->getPrice('ROZ')->format());
        return [
            'meta' => [
                'title' => $this->invoker->call(
                    $this->config->get('productMetaTitleCallback') ?? function (Product $product) {
                    return sprintf(
                        '%2$s - %3$s - %1$s',
                        $this->setting->get('sitename'),
                        $product->getMeta()?->getTitle() ?? $this->product->getName(),
                        $product->getCategory()?->getFullTitle(reverse: true) ?? 'Каталог'
                    );
                },
                    ['product' => $this->product]
                ),
                'keywords' => $this->invoker->call(
                    $this->config->get('productMetaKeywordsCallback') ?? function (Product $product) {
                    return $product->getMeta()?->getKeyword() ?? $this->metaGenerator->generateProductKeywords(
                        $product
                    );
                },
                    ['product' => $this->product]
                ),
                'description' => $this->invoker->call(
                    $this->config->get('productMetaDescriptionCallback') ?? function (Product $product) {
                    return $product->getMeta()?->getDescription() ?? $this->metaGenerator->generateProductDescription(
                        $product
                    );
                },
                    ['product' => $this->product]
                ),
            ],
            'product' => $this->product,
            'productRepository' => $this->productRepository,
            'optionKeyRepository' => $this->em->getRepository(OptionKey::class),
            'optionValueRepository' => $this->em->getRepository(OptionValue::class),
            'priceGroups' => $this->em->getRepository(PriceGroup::class)->findAll(),
            'breadcrumbs' => $this->getBreadcrumbs()
        ];
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     * @throws NotSupported
     */
    private function getProduct(): Product
    {
        $product = $this->productRepository->findBySlug($this->request->getAttribute('slug', ''));
        if ($product === null) {
            throw new NoResultException();
        }
        $prepareProductModel = new PrepareProductModel(
            product: $product,
            optionKeyRepository: $this->optionKeyRepository,
            config: $this->config
        );
        return $prepareProductModel->getProduct();
    }

    private function getBreadcrumbs(): array
    {
        $this->breadcrumbs->add($this->urlGenerator->generate('catalog/index'), 'Каталог');
        $breadcrumbs = $this->product->getCategory()?->getBreadcrumbs();
        foreach ((array)$breadcrumbs as $breadcrumb) {
            $this->breadcrumbs->add(
                $this->urlGenerator->generate('catalog/category', ['slug' => $breadcrumb['slug']]),
                $breadcrumb['title']
            );
        }
        $this->breadcrumbs->add(null, $this->product->getName());
        return $this->breadcrumbs->getBreadcrumbs();
    }

    public function getProductEntity(): Product
    {
        return $this->product;
    }


}
