<?php

namespace EnjoysCMS\Module\Catalog\Controller\Api\V1;

use DI\Container;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Enum\OrderByDirection;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[Route('/api/v1/catalog/category', 'api_v1_catalog_category_')]
class Category extends AbstractController
{

    private Serializer $serializer;

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $this->serializer = new Serializer(
            normalizers: [
                new ObjectNormalizer(
                    classMetadataFactory: new ClassMetadataFactory(new AttributeLoader()),
                    nameConverter: new CamelCaseToSnakeCaseNameConverter()
                )
            ],
            encoders: [new JsonEncoder()]
        );
    }

    #[Route('/tree', 'tree', methods: ['GET'])]
    public function getCategories(\EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository): ResponseInterface
    {
        $node = null;
        $criteria = [];
        $orderBy = 'sort';
        $direction = OrderByDirection::ASC;

        return match ($this->request->getQueryParams()['mode'] ?? null) {
            'flat' => $this->json(array_merge(['Все категории'],
                $categoryRepository->getFormFillArray(
                    $node,
                    $criteria,
                    $orderBy,
                    $direction->name
                ))),
            default => $this->json(
                $this->serializer->normalize(
                    $categoryRepository->getChildNodes($node, $criteria, $orderBy, $direction->name),
                    'json',
                    $this->getSerializationContext()
                )
            )
        };
    }

    private function getSerializationContext(array $groups = ['public']): array
    {
        return [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return match ($object::class) {
                    \EnjoysCMS\Module\Catalog\Entity\Category::class => $object->getTitle(),
                };
            },
            AbstractNormalizer::GROUPS => $groups,
        ];
    }
}
