<?php

namespace EnjoysCMS\Module\Catalog\Service\Search;

final class SearchQuery
{

    public function __construct(
        public readonly string $query,
        public readonly array $optionKeys = []
    ) {

    }


}
