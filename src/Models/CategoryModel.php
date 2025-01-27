<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Models;

use DI\DependencyException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ObjectRepository;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Breadcrumbs\BreadcrumbCollection;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Core\Http\Response\RedirectInterface;
use EnjoysCMS\Core\Pagination\Pagination;
use EnjoysCMS\Core\Setting\Setting;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductPriceEntityListener;
use EnjoysCMS\Module\Catalog\ORM\Doctrine\Functions\ConvertPrice;
use EnjoysCMS\Module\Catalog\Repository;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterFactory;
use Invoker\Exception\InvocationException;
use Invoker\Exception\NotCallableException;
use Invoker\Exception\NotEnoughParametersException;
use Invoker\InvokerInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;

final class CategoryModel implements ModelInterface
{

    private Category $category;
    private Pagination $pagination;

    /**
     * @throws NotFoundException
     * @throws NotSupported
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function __construct(
        private readonly EntityManager $em,
        private readonly InvokerInterface $invoker,
        private readonly Repository\Category $categoryRepository,
        private readonly Repository\Product $productRepository,
        private readonly ServerRequestInterface $request,
        private readonly BreadcrumbCollection $breadcrumbs,
        private readonly RedirectInterface $redirect,
        private readonly FilterFactory $filterFactory,
        private readonly Identity $identity,
        private readonly Config $config,
        private readonly Setting $setting,
    ) {

        $this->category = $this->categoryRepository->findByPath(
            $this->request->getAttribute('slug', '')
        ) ?? throw new NotFoundException(
            sprintf('Not found by slug: %s', $this->request->getAttribute('slug', ''))
        );

        $this->pagination = new Pagination(
            $this->request->getAttribute('page', 1),
            $this->config->getPerPage()
        );

        $globalExtraFields = array_filter(
            array_map(function ($item) {
                return $this->em->getRepository(OptionKey::class)->find($item);
            }, explode(',', $this->config->getGlobalExtraFields()))
        );

        $this->config->getGlobalExtraFields();
        $this->config->getSearchOptionField();
        $this->config->getGlobalExtraFields();
        $this->config->getSearchOptionField();

        foreach ($globalExtraFields as $globalExtraField) {
            $this->category->addExtraField($globalExtraField);
        }

        $entityListenerResolver = $this->em->getConfiguration()->getEntityListenerResolver();
        $entityListenerResolver->register(new ProductPriceEntityListener($this->config));

        $this->em->getConfiguration()->addCustomStringFunction('CONVERT_PRICE', ConvertPrice::class);

        $this->updateConfigValues();

        $this->breadcrumbs->add('catalog/index', 'Каталог');
        foreach ($this->category->getBreadcrumbs() as $breadcrumb) {
            $this->breadcrumbs->add(
                ['catalog/category', ['slug' => $breadcrumb['slug']]],
                $breadcrumb['title']
            );
        }
    }


    /**
     * @throws DependencyException
     * @throws InvocationException
     * @throws NotCallableException
     * @throws NotEnoughParametersException
     * @throws NotFoundException
     * @throws \DI\NotFoundException
     * @throws Exception
     */
    public function getContext(): array
    {
        if ($this->config->get('showSubcategoryProducts', false)) {
            $allCategoryIds = $this->categoryRepository->getAllIds($this->category);
            $qb = $this->productRepository->getFindByCategorysIdsDQL($allCategoryIds);
        } else {
            $qb = $this->productRepository->getQueryBuilderFindByCategory($this->category);
        }

        // Filter goods
        $filtered = false;
        $filtersQueryString = $this->request->getQueryParams()['filter'] ?? false;
        $usedFilters = [];
        if (!empty($filtersQueryString) && is_array($filtersQueryString)) {
            $usedFilters = $this->filterFactory->createFromQueryString($filtersQueryString);

            foreach ($usedFilters as $filter) {
                $qb = $filter->addFilterQueryBuilderRestriction($qb);
            }
        }
//dd($usedFilters);
        $qb->andWhere('p.hide = false');
        $qb->andWhere('p.active = true');

        if (false !== $o = $this->config->get('withImageFirst', false)) {
            $qb->orderBy('i.filename', strtoupper($o));
        }

        if (!in_array($this->em->getConnection()->getDatabasePlatform()::class, [SqlitePlatform::class])) {
            $qb->addSelect('CONVERT_PRICE(pr.price, pr.currency, :current_currency) as HIDDEN converted_price');
            $qb->setParameter('current_currency', $this->config->getCurrentCurrencyCode());
        } else {
            $qb->addSelect('pr.price as HIDDEN converted_price');
        }


        $qb->addSelect('w')
            ->leftJoin('p.wishlist', 'w', Join::WITH, 'w.product = p.id and w.user = :user')
            ->setParameter('user', $this->identity->getUser());

        /** Показывает товары не в наличии в самом конце списка */
        if ($this->config->get('moveProductsOutOfStockToEnd', false)) {
            $qb->addSelect('CASE WHEN (q.qty - q.reserve) > 0 THEN true ELSE false END as HIDDEN realQty');
            $qb->addOrderBy('realQty', 'DESC');
        }

        match ($this->config->getSortMode()) {
            'price.desc' => $qb->addOrderBy('converted_price', 'DESC'),
            'price.asc' => $qb->addOrderBy('converted_price', 'ASC'),
            'name.desc' => $qb->addOrderBy('p.name', 'DESC'),
            'custom' => $this->addCustomOrderBy($qb),
            default => $qb->addOrderBy('p.name', 'ASC'),
        };

        $qb->setFirstResult($this->pagination->getOffset())->setMaxResults($this->pagination->getLimitItems());

        $result = new Paginator($qb->getQuery());
        $this->pagination->setTotalItems($result->count());

        if ($this->config->get('redirectCategoryToProductIfIsOne', false) && $this->pagination->getTotalItems() === 1) {
            $redirectAllow = false;

            if ($this->config->get('allowRedirectForAllCategories', false)) {
                $redirectAllow = true;
            }

            if ($redirectAllow === false && in_array(
                    $this->category->getId(),
                    $this->config->get('categoriesIdForRedirectToProductIfIsOne', []),
                    true
                )) {
                $redirectAllow = true;
            }

            if ($redirectAllow === true) {
                $this->redirect->toRoute('catalog/product', [
                    'slug' => $result->getIterator()->current()->getSlug()
                ], 301, true);
            }
        }


        return [
            'meta' => $this->getMeta(),
            'category' => $this->category,
            'categoryRepository' => $this->categoryRepository,
            'pagination' => $this->pagination,
            'products' => $result,
            'config' => $this->config,
            'setting' => $this->setting,
            'breadcrumbs' => $this->breadcrumbs,
            'usedFilters' => $usedFilters,
            'filtered' => $usedFilters !== []
        ];
    }


    private function updateConfigValues(): void
    {
        $this->updatePerPage();
        $this->updateSortMode();
    }

    private function updateSortMode(): void
    {
        $mode = $this->request->getQueryParams()['sort'] ?? $this->request->getParsedBody()['sort'] ?? null;
        if ($mode !== null) {
            $this->config->setSortMode($mode);
        }
    }

    private function updatePerPage(): void
    {
        $perpage = $this->request->getQueryParams()['perpage'] ?? $this->request->getParsedBody()['perpage'] ?? null;
        if ($perpage !== null) {
            $this->config->setPerPage($perpage);
        }
    }

    private function addCustomOrderBy(QueryBuilder $qb): void
    {
        foreach (
            $this->config->get('customSortParams') ?? throw new RuntimeException(
            'Need set customSortParams'
        ) as $order
        ) {
            $qb->addOrderBy($order['column'], $order['direction']);
        }
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    public function getPagination(): Pagination
    {
        return $this->pagination;
    }

    /**
     * @return array
     * @throws InvocationException
     * @throws NotCallableException
     * @throws NotEnoughParametersException
     */
    public function getMeta(): array
    {
        return [
            'title' => $this->category->getMeta()->getTitle() ?? $this->invoker->call(
                    $this->config->get('categoryMetaTitleCallback') ?? function (
                    Category $category,
                    Pagination $pagination
                ) {
                    return strtr('{category} - {sitename} ({currentPage}/{totalPage})', [
                        '{category}' => $category->getFullTitle(reverse: true),
                        '{sitename}' => $this->setting->get('sitename'),
                        '{currentPage}' => $pagination->getCurrentPage(),
                        '{totalPage}' => $pagination->getTotalPages(),
                    ]);
                },
                    ['category' => $this->category, 'pagination' => $this->pagination,]
                ),
            'description' => $this->invoker->call(
                $this->config->get('categoryMetaDescriptionCallback') ?? function (Category $category) {
                return $category->getMeta()->getDescription();
            },
                ['category' => $this->category, 'pagination' => $this->pagination,]
            ),
            'keywords' => $this->category->getMeta()->getKeyword() ?? $this->invoker->call(
                    $this->config->get('categoryMetaKeywordsCallback') ?? function (Category $category) {
                    return $category->getMeta()->getKeyword();
                },
                    ['category' => $this->category, 'pagination' => $this->pagination,]
                ),
        ];
    }

}
