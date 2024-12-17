<?php

namespace EnjoysCMS\Module\Catalog\Service\Search;

final class SearchQuery
{

    public function __construct(
        public readonly string $query,
        public readonly array $optionKeys = [],
        private ?string $category = null,
    ) {
        if ($this->category === 'all' || (int)$this->category === 0) {
            $this->category = null;
        }
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }


}
