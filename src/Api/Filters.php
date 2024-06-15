<?php

namespace EnjoysCMS\Module\Catalog\Api;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Module\Catalog\Entity\CategoryFilter;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterFactory;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route(
    path: 'admin/catalog/api/filters/get',
    name: '@catalog_api_get_filters',
    options: [
        'comment' => 'API: получение списка фильтров категорий '
    ],
    methods: [
        'GET'
    ]
)]
class Filters extends AbstractController
{

    public function __construct(
        Container $container,
        private readonly FilterFactory $filterFactory,
    ) {
        parent::__construct($container);
    }


    /**
     * @throws DependencyException
     * @throws NotFoundException
     * @throws NotSupported
     */
    public function __invoke(
        EntityManager $em,
    ): ResponseInterface {
        /** @var CategoryFilter[] $filters */
        $filters = $em->getRepository(CategoryFilter::class)->findBy([
            'category' => $this->request->getQueryParams()['category'] ?? throw new \InvalidArgumentException(
                    'Category id not sent'
                )
        ], ['order' => 'asc']);

        return $this->json($this->normalizeData($filters));
    }

    /**
     * @param CategoryFilter[] $filters
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function normalizeData(array $filters): array
    {
        $result = [];
        foreach ($filters as $filterMetaData) {
            $filter = $this->filterFactory->create($filterMetaData->getFilterType(), $filterMetaData->getParams());
            if ($filter === null) {
                continue;
            }
            $result[] = [
                'id' => $filterMetaData->getId(),
                'order' => $filterMetaData->getOrder(),
                'filterType' => $filterMetaData->getFilterType(),
                'filterParams' => $filterMetaData->getParams()->getParams(),
                'filterTitle' => $filter->__toString(),
            ];
        }
        return $result;
    }
}
