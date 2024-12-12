<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Service\Search;


use Countable;
use EnjoysCMS\Module\Catalog\Entity\Product;
use Traversable;

final class SearchResult
{



    /**
     * @param iterable<Product>&Countable $products
     */
    public function __construct(
        private readonly SearchQuery $searchQuery,
        private array $errors = [],
        private iterable  $products = []
    ) {
    }

    /**
     * @return iterable<Product>&Countable
     */
    public function getProducts(): iterable
    {
        return $this->products;
    }

    /**
     * @param iterable<Product> $products
     */
    public function setProducts(iterable $products): void
    {
        $this->products = $products;
    }

    public function addError(\Throwable|\Exception $e): void
    {
        $this->errors[] = $e;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }

    public function getSearchQuery(): SearchQuery
    {
        return $this->searchQuery;
    }
}
