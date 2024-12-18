<?php

namespace EnjoysCMS\Module\Catalog\Service\Search;

use EnjoysCMS\Module\Catalog\Entity\Category;

final class SearchQuery
{

    public function __construct(
        public readonly string $query,
        public readonly array $optionKeys = [],
        public ?Category $category = null,
    ) {

    }



}
