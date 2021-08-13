<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Models;


use DI\FactoryInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ObjectRepository;
use Enjoys\Http\ServerRequestInterface;
use EnjoysCMS\Core\Components\Breadcrumbs\BreadcrumbsInterface;
use EnjoysCMS\Core\Components\Helpers\Error;
use EnjoysCMS\Core\Components\Helpers\Setting;
use EnjoysCMS\Module\Catalog\Entities\Product;
use EnjoysCMS\Module\Catalog\Repositories;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProductModel implements ModelInterface
{

    private ObjectRepository|EntityRepository|Repositories\Product $productRepository;
    private Product $product;

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function __construct(
        private EntityManager $em,
        private ServerRequestInterface $serverRequest,
        private BreadcrumbsInterface $breadcrumbs,
        private UrlGeneratorInterface $urlGenerator,
        private SendMail $sendMail
    ) {
        $this->productRepository = $this->em->getRepository(Product::class);
        $this->product = $this->getProduct();
    }

    #[ArrayShape([
        '_title' => "string",
        'product' => "\EnjoysCMS\Module\Catalog\Entities\Product",
        'breadcrumbs' => "array",
        'sendMailForm' => "\Enjoys\Forms\Form"
    ])]
    public function getContext(): array
    {
        return [
            '_title' => sprintf(
                '%2$s - %3$s - %1$s',
                Setting::get('sitename'),
                $this->product->getName(),
                $this->product->getCategory()->getFullTitle(reverse: true)
            ),
            'product' => $this->product,
            'breadcrumbs' => $this->getBreadcrumbs(),
            'sendMailForm' => $this->sendMail->getForm()
        ];
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    private function getProduct(): Product
    {
        $product = $this->productRepository->findBySlug($this->serverRequest->get('slug'));
        if ($product === null) {
            throw new NoResultException();
        }
        return $product;
    }

    private function getBreadcrumbs(): array
    {
        $this->breadcrumbs->add($this->urlGenerator->generate('catalog/index'), 'Каталог');
        foreach ($this->product->getCategory()->getBreadcrumbs() as $breadcrumb) {
            $this->breadcrumbs->add(
                $this->urlGenerator->generate('catalog/category', ['slug' => $breadcrumb['slug']]),
                $breadcrumb['title']
            );
        }
        $this->breadcrumbs->add(null, $this->product->getName());
        return $this->breadcrumbs->get();
    }
}