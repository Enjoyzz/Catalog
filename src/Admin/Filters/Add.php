<?php

namespace EnjoysCMS\Module\Catalog\Admin\Filters;

use Doctrine\ORM\EntityManagerInterface;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\CategoryFilter;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use stdClass;

#[Route(
    path: 'admin/catalog/filters/add',
    name: '@catalog_filters_add',
    methods: [
        'PUT'
    ]
)]
class Add
{
    private stdClass $input;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ResponseInterface $response,
        private readonly EntityManagerInterface $em,
    ) {
        $this->input = json_decode($this->request->getBody()->getContents());
    }

    public function __invoke(): ResponseInterface
    {
        $response = $this->response->withHeader('content-type', 'application/json');

        /** @var Category $category */
        $category = $this->em->getRepository(Category::class)->find(
            $this->input->category ?? throw new InvalidArgumentException('category id not found')
        ) ?? throw new RuntimeException('Category not found');

        switch ($this->input->filterType) {
            case 'option':
                $this->addOptionFilter($category);
                break;
            case 'price':
            case 'stock':
            case 'vendor':
                $this->addFilter($category);
                break;
        }
        $this->em->flush();

        return $response;
    }

    private function addOptionFilter(Category $category): void
    {
        foreach ($this->input->options ?? [] as $optionKeyId) {
            $hash = md5($this->input->filterType . $optionKeyId);

            $this->addFilter($category, [
                'optionKey' => $optionKeyId
            ], $hash);
        }
    }

    private function addFilter(Category $category, array $params = null, string $hash = null): void
    {
        $hash ??= md5($this->input->filterType);
        if ($this->isFilterExist($category, $this->input->filterType, $hash)) {
            return;
        }
        $filter = new CategoryFilter();
        $filter->setCategory($category);
        $filter->setFilterType($this->input->filterType);
        $filter->setParams((array)($params ?? $this->input->filterParams ?? []));
        $filter->setOrder($this->input->order ?? 0);
        $filter->setHash($hash);

        $this->em->persist($filter);
    }

    public function isFilterExist(Category $category, string $filterType, string $hash): bool
    {
        return null !== $this->em->getRepository(CategoryFilter::class)->findOneBy(
                ['category' => $category, 'filterType' => $filterType, 'hash' => $hash]
            );
    }


}
