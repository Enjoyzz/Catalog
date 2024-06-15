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
    path: 'admin/catalog/filters/update',
    name: '@catalog_filters_update',
    methods: [
        'PATCH'
    ]
)]
class Update
{
    private stdClass $input;

    public function __construct(
        private readonly ServerRequestInterface $request,
        private ResponseInterface $response,
        private readonly EntityManagerInterface $em,
    ) {
        $this->input = json_decode($this->request->getBody()->getContents());
        $this->response = $this->response->withHeader('content-type', 'application/json');
    }

    public function __invoke(): ResponseInterface
    {
        /** @var Category $category */
        $filter = $this->em->getRepository(CategoryFilter::class)->find(
            $this->input->filterId ?? throw new InvalidArgumentException('Filter id not found')
        ) ?? throw new RuntimeException('Filter not found');

        $filter->setOrder((int)($this->input->order ?? 0));
        $filter->setParams(array_merge($filter->getParams()->getParams(), (array)$this->input->filterParams));

        $this->em->flush();
        return $this->response;
    }
}
