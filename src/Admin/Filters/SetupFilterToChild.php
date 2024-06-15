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
    path: 'admin/catalog/filters/setup-to-child',
    name: '@catalog_filters_setup_to_child',
    methods: [
        'POST'
    ]
)]
class SetupFilterToChild
{
    private stdClass $input;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private ResponseInterface $response,
        private readonly EntityManagerInterface $em,
    ) {
        $this->input = json_decode($this->request->getBody()->getContents());
//        $this->input = json_decode(json_encode($this->request->getQueryParams()));
        $this->response = $this->response->withHeader('content-type', 'application/json');
    }

    public function __invoke(): ResponseInterface
    {
        /** @var \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository */
        $categoryRepository = $this->em->getRepository(Category::class);

        $filterRepository = $this->em->getRepository(CategoryFilter::class);

        /** @var Category $category */
        $filter = $this->em->getRepository(CategoryFilter::class)->find(
            $this->input->filterId ?? throw new InvalidArgumentException('Filter id not found')
        ) ?? throw new RuntimeException('Filter not found');


        /** @var Category $category */
        $category = $categoryRepository->find(
            $this->input->category ?? throw new InvalidArgumentException('category id not found')
        ) ?? throw new RuntimeException('Category not found');

        /** @var Category[] $children */
        $children = $categoryRepository->getChildren($category);

        foreach ($children as $child) {
            $cloneFilter = clone $filter;

            if ($filterRepository->findOneBy([
                        'category' => $child,
                        'filterType' => $cloneFilter->getFilterType(),
                        'hash' => $cloneFilter->getHash()
                    ]
                ) !== null) {
                continue;
            }
            $cloneFilter->setCategory($child);
            $this->em->persist($cloneFilter);
        }

        $this->em->flush();
        return $this->response;
    }
}
