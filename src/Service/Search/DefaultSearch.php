<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Service\Search;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Module\Catalog\Repository\Category;
use EnjoysCMS\Module\Catalog\Repository\Product;
use Exception;

final class DefaultSearch implements SearchInterface
{

    private ?string $error = null;

    public function __construct(
        private readonly Product $productRepository,
        private readonly Category $categoryRepository,
    ) {
    }


    public function setError(string $error = null): void
    {
        $this->error = $error;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * @throws Exception
     */
    public function getResult(SearchQuery $searchQuery, int $offset, int $limit): SearchResult
    {
        $products = new Paginator(
            $this
                ->getFoundProductsQueryBuilder(
                    $searchQuery->query,
                    $searchQuery->optionKeys,
                    $searchQuery->getCategory()
                )
                ->setFirstResult($offset)
                ->setMaxResults($limit)
        );


        return new SearchResult(
            searchQuery: $searchQuery,
            products: $products
        );
    }


    private function getFoundProductsQueryBuilder(
        string $searchQuery,
        array $optionKeys = [],
        ?string $category = null
    ): QueryBuilder {
        $qb = $this->productRepository->createQueryBuilder('p')
            ->select('p', 'm', 'u', 'ov', 'c')
            ->leftJoin('p.meta', 'm')
            ->leftJoin('p.urls', 'u')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.options', 'ov', Join::WITH, 'ov.optionKey IN (:key) ')
            ->where('p.name LIKE :option')
            ->orWhere('p.description LIKE :option')
            ->orWhere('c.title LIKE :option')
            ->orWhere('ov.value LIKE :option')
            ->andWhere('p.active = true')
            ->andWhere('c.status = true OR c IS null')
            ->setParameters([
                'key' => $optionKeys,
                'option' => '%' . $searchQuery . '%'
            ])//
        ;

//        dd($this->categoryRepository->getAllIds($this->categoryRepository->find($category)));
//        dd($category)
        if ($category !== null) {
            $qb->andWhere('p.category IN (:ids)')
                ->setParameter('ids', $this->categoryRepository->getAllIds($this->categoryRepository->find($category)));
        }
        return $qb;
    }


}
