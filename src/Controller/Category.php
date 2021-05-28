<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ObjectRepository;
use Enjoys\Http\ServerRequestInterface;
use Enjoys\Traits\Options;
use EnjoysCMS\Core\Components\Breadcrumbs\BreadcrumbsInterface;
use EnjoysCMS\Core\Components\Helpers\Error;
use EnjoysCMS\Core\Components\Helpers\Setting;
use EnjoysCMS\Core\Components\Pagination\Pagination;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entities\Product;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

final class Category
{

    use Options;

    /**
     * @var EntityRepository|ObjectRepository|\EnjoysCMS\Module\Catalog\Repositories\Category
     */
    private $categoryRepository;
    private ServerRequestInterface $serverRequest;
    private Environment $twig;
    /**
     * @var EntityRepository|ObjectRepository|\EnjoysCMS\Module\Catalog\Repositories\Product
     */
    private $productRepository;
    private UrlGeneratorInterface $urlGenerator;
    private EntityManager $entityManager;


    public function __construct(
        ServerRequestInterface $serverRequest,
        EntityManager $entityManager,
        Environment $twig,
        UrlGeneratorInterface $urlGenerator,
        ContainerInterface $container
    ) {
        $this->categoryRepository = $entityManager->getRepository(\EnjoysCMS\Module\Catalog\Entities\Category::class);
        $this->productRepository = $entityManager->getRepository(\EnjoysCMS\Module\Catalog\Entities\Product::class);
        $this->serverRequest = $serverRequest;
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;

        $this->setOptions($this->config = Config::getConfig($container)->getAll());
        $this->entityManager = $entityManager;
    }

    /**
     * @Route(
     *     name="catalog/category",
     *     path="catalog/{slug}@{page}",
     *     defaults={
     *      "page": 1
     *     },
     *     requirements={
     *      "slug": "[^.^@]+",
     *      "page": "\d+"
     *     },
     *     options={
     *      "aclComment": "[public] Просмотр категорий"
     *     }
     * )
     * @throws \Exception
     */
    public function view(ContainerInterface $container): string
    {
        /** @var \EnjoysCMS\Module\Catalog\Entities\Category $category */
        $category = $this->categoryRepository->findByPath($this->serverRequest->get('slug'));
        if ($category === null) {
            Error::code(404);
        }

        $breadcrumbs = $container->get(BreadcrumbsInterface::class);
        $breadcrumbs->add($this->urlGenerator->generate('catalog/index'), 'Каталог');
        foreach ($category->getBreadcrumbs() as $breadcrumb) {
            $breadcrumbs->add(
                $this->urlGenerator->generate('catalog/category', ['slug' => $breadcrumb['slug']]),
                $breadcrumb['title']
            );
        }

//        $products = $this->productRepository->findByCategory($category);
        $pagination = new Pagination($this->serverRequest->get('page', 1), $this->getOption('limitItems'));

        if ($this->getOption('showSubcategoryProducts', false)) {
            $allCategoryIds = $this->entityManager->getRepository(
                \EnjoysCMS\Module\Catalog\Entities\Category::class
            )->getAllIds($category)
            ;
            $qb = $this->productRepository
                ->getFindByCategorysIdsQuery($allCategoryIds);
        } else {
            $qb = $this->productRepository
                ->getQueryFindByCategory($category);
        }


        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults($pagination->getLimitItems())
        ;
        $result = new Paginator($qb);


        $pagination->setTotalItems($result->count());

        $template_path = '@m/catalog/category.twig';

        if (!$this->twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/category.twig.sample';
        }

        return $this->twig->render(
            $template_path,
            [
                '_title' => sprintf(
                    '%2$s - %1$s',
                    Setting::get('sitename'),
                    $category->getFullTitle(reverse: true)
                ),
                'category' => $category,
                'pagination' => $pagination,
                'products' => $result,
                'breadcrumbs' => $breadcrumbs->get(),
            ]
        );
    }

}