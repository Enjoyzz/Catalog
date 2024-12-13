<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Service\Search;


interface SearchInterface
{

    public function getResult(SearchQuery $searchQuery, int $offset, int $limit): SearchResult;

    public function setError(string $error = null): void;

    public function getError(): ?string;
}
