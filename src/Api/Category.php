<?php

namespace EnjoysCMS\Module\Catalog\Api;

use DI\Container;
use Doctrine\Migrations\Version\Direction;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\QueryException;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\Category\SaveCategoryStructure;
use Psr\Http\Message\ResponseInterface;
use Throwable;

use function json_decode;

/**
 * @deprecated
 */
#[Route('admin/catalog/api/category', '@catalog_admin_api~category_')]
class Category extends AbstractController
{

    public function __construct(Container $container, private readonly EntityManagerInterface $em)
    {
        parent::__construct($container);
    }

    /**
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    #[Route(
        path: '/get/tree',
        name: 'get_tree',
        methods: [
            'GET'
        ],
        comment: 'API: получение списка категорий '
    )]
    public function getCategoryListForFormSelectElement(): ResponseInterface
    {
        /** @var \EnjoysCMS\Module\Catalog\Repository\Category|EntityRepository $categoryRepository */
        $categoryRepository = $this->em->getRepository(\EnjoysCMS\Module\Catalog\Entity\Category::class);

        $node = null;
        $criteria = [];
        $orderBy = 'sort';
        $direction = 'asc';

        $categories = $categoryRepository->getFormFillArray(
            $node,
            $criteria,
            $orderBy,
            $direction
        );

//dd($categories);
        return $this->json(
            array_merge([['id' => 0, 'title' => 'Все категории']],
                array_map(function ($id, $title) {
                    return [
                        'id' => (string)$id,
                        'title' => $title
                    ];
                }, array_keys($categories), $categories))
        );
    }

    #[Route(
        path: '/structure/save',
        name: 'save_structure',
        methods: [
            'post'
        ],
        comment: 'Редактирование категорий (структура, json)'
    )]
    public function saveCategoryStructure(): ResponseInterface
    {
        try {
            $this->container->call(
                SaveCategoryStructure::class,
                ['data' => json_decode($this->request->getBody()->getContents())]
            );
            $this->em->flush();
            return $this->json('saved');
        } catch (Throwable $e) {
            return $this->json($e->getMessage(), 401);
        }
    }

    /**
     * @throws NotSupported
     * @throws NoResultException
     */
    #[Route(
        path: '/extra-fields/get',
        name: 'get_extra_fields',
        comment: '[JSON] Получение списка extra fields'
    )]
    public function getExtraFieldsJson(): ResponseInterface
    {
        $result = [];

        /** @var \EnjoysCMS\Module\Catalog\Entity\Category $category */
        $category = $this->em->getRepository(\EnjoysCMS\Module\Catalog\Entity\Category::class)->find(
            $this->request->getParsedBody()['id'] ?? ''
        );

        if ($category === null) {
            throw new NoResultException();
        }

        $extraFields = $category->getParent()?->getExtraFields() ?? [];

        foreach ($extraFields as $key) {
            $result[$key->getId()] = $key->getName() . (($key->getUnit()) ? ' (' . $key->getUnit() . ')' : '');
        }

        return $this->json($result);
    }

}
