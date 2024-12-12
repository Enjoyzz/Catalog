<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Service\Search;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Module\Catalog\Repository\Product;
use Exception;

final class DefaultSearch implements SearchInterface
{

    private ?array $errors = null;

    private SearchQuery $searchQuery;

    public function __construct(
        private readonly Product $productRepository
    ) {
    }

    public function setSearchQuery(SearchQuery $searchQuery): void
    {
        $this->searchQuery = $searchQuery;
    }

    public function setErrors(array $errors = null): void
    {
        $this->errors = $errors;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @throws Exception
     */
    public function getResult(int $offset, int $limit): SearchResult
    {
        $products = new Paginator(
            $this
                ->getFoundProductsQueryBuilder(
                    $this->searchQuery->query,
                    $this->searchQuery->optionKeys
                )
                ->setFirstResult($offset)
                ->setMaxResults($limit)
        );


        return new SearchResult(
            searchQuery: $this->searchQuery,
            products: $products
        );
    }


    private function getFoundProductsQueryBuilder(string $searchQuery, array $optionKeys = []): QueryBuilder
    {
        return $this->productRepository->createQueryBuilder('p')
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
    }




}
