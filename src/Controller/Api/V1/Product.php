<?php

namespace EnjoysCMS\Module\Catalog\Controller\Api\V1;

use DI\Container;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Auth\IdentityInterface;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\Image;
use EnjoysCMS\Module\Catalog\Entity\ProductPrice;
use EnjoysCMS\Module\Catalog\Entity\Url;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[Route('/api/v1/catalog/product', 'api_v1_catalog_product_')]
class Product extends AbstractController
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly IdentityInterface $identity,
        Container $container
    ) {
        parent::__construct($container);
    }

    private function getProductSerializationContext(?array $attributes = null): array
    {
        return [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return match ($object::class) {
                    Category::class => $object->getTitle(),
                    \EnjoysCMS\Module\Catalog\Entity\Product::class => $object->getName(),
                };
            },
            AbstractNormalizer::ATTRIBUTES => $attributes ?? [
                    'id',
                    'name',
                    'hide',
                    'active',
                    'sku',
                    'vendor' => [
                        'id',
                        'name'
                    ],
                    'vendorCode',
                    'barCodes',
                    'category' => [
                        'title',
                        'fullTitle',
                        'slug',
                        'breadcrumbs'
                    ],
                    'slug',
                    'urls',
                    'prices',
                    'defaultImage',
                    'images',
                    'quantity' => [
                        'qty',
                        'reserve',
                        'realQty',
                        'step',
                        'min'
                    ]
                ],
            AbstractNormalizer::CALLBACKS => [
                'category.fullTitle' => function (Category $category) {
                    return $category->getFullTitle();
                },
                'defaultImage' => function (?Image $image) {
                    if ($image === null) {
                        return null;
                    }
                    return $image->getUrlsStack($this->config);
                },
                'images' => function (Collection $images) {
                    return array_map(function (Image $image) {
                        return $image->getUrlsStack($this->config);
                    }, $images->toArray());
                },
                'prices' => function (Collection $prices) {
                    $result = [];
                    foreach ($prices as $price) {
                        /** @var ProductPrice $price */

                        if ($this->identity->getUser()->isAdmin()) {
                            $result[$price->getPriceGroup()->getCode()] = [
                                'price' => $price->getPrice(),
                                'currency' => $price->getCurrency()->getCode(),
                                'format' => $price->format()
                            ];
                            continue;
                        }

                        if (in_array(
                            $price->getPriceGroup()->getCode(),
                            [$this->config->getDefaultPriceGroup(), $this->config->getOldPriceGroupName()],
                            true
                        )) {
                            $result[$price->getPriceGroup()->getCode()] = [
                                'price' => $price->getPrice(),
                                'currency' => $price->getCurrency()->getCode(),
                                'format' => $price->format()
                            ];
                        }
                    }
                    return $result;
                },
                'urls' => function (Collection $urls) {
                    return array_map(function ($url) {
                        /** @var Url $url */
                        return $this->urlGenerator->generate('catalog/product', [
                            'slug' => $url->getProduct()->getSlug($url->getPath())
                        ]);
                    }, $urls->toArray());
                }
            ],
        ];
    }

    /**
     * @throws ExceptionInterface
     * @throws NotFoundException
     */
    #[Route('/{id}', 'get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function getProduct(\EnjoysCMS\Module\Catalog\Repository\Product $productRepository): ResponseInterface
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer(nameConverter: new CamelCaseToSnakeCaseNameConverter())],
            encoders: [new JsonEncoder()]
        );
        $product = $productRepository->find($this->request->getAttribute('id'));
        if ($product === null) {
            throw new NotFoundException();
        }
        return $this->json(
            $serializer->normalize(
                $product,
                JsonEncoder::FORMAT,
                context: $this->getProductSerializationContext(
                    $this->getSerializationAttributes()
                )
            )
        );
    }

    /**
     * @throws ExceptionInterface
     * @throws QueryException
     */
    #[Route('s', 'get_products', methods: ['GET'])]
    public function getProducts(
        \EnjoysCMS\Module\Catalog\Repository\Product $productRepository,
        \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository
    ): ResponseInterface {
        $criteria = [];
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer(nameConverter: new CamelCaseToSnakeCaseNameConverter())],
            encoders: [new JsonEncoder()]
        );
        $limit = (int)($this->request->getQueryParams()['limit'] ?? 10);

        $page = (int)($this->request->getQueryParams()['page']
            ?? (($this->request->getQueryParams()['offset'] ?? 0) / $limit) + 1);

        $offset = ($page - 1) * $limit;

        $category = $categoryRepository->find($this->request->getQueryParams()['category'] ?? null);
        if ($category !== null) {
            $criteria[] = Criteria::create()
                ->where(
                    Criteria::expr()->in(
                        'p.category',
                        $categoryRepository->getAllIds($category)
                    )
                );
        }

        $criteria[] = $this->getSearchCriteria();

        $orders = ['p.id' => 'desc'];
        foreach ($this->request->getQueryParams()['order'] ?? [] as $item) {
            $orders[$this->request->getQueryParams()['columns'][$item['column']]['name']] = $item['dir'];
        }

        $products = new Paginator(
            $productRepository->getProductsQuery(
                offset: $offset,
                limit: $limit,
                criteria: array_filter($criteria),
                orders: $orders
            )
        );

        return $this->json([
            'draw' => (int)($this->request->getQueryParams()['draw'] ?? null),
            'total' => $products->count(),
            'offset' => $offset,
            'limit' => $limit,
            'page' => $page,
            'products' => $serializer->normalize(
                $products,
                JsonEncoder::FORMAT,
                context: $this->getProductSerializationContext()
            )

        ]);
    }

    /**
     * @param array $criteria
     * @return array
     */
    private function getSearchCriteria(): ?Criteria
    {
        $searchQuery = (empty(
            $this->request->getQueryParams()['search']['value'] ?? null
        )) ? null : $this->request->getQueryParams()['search']['value'];

        if ($searchQuery !== null) {
            $criteria = Criteria::create();
            foreach ($this->config->get('admin->searchFields', []) as $field) {
                $criteria->orWhere(Criteria::expr()->contains($field, $searchQuery));
            }
            return $criteria;
        }
        return null;
    }

    private function getSerializationAttributes(): ?array
    {
        if ($this->identity->getUser()->isAdmin()) {
            return null;
        }

        return [
            'id',
            'name',
            'sku',
            'vendor' => [
                'id',
                'name'
            ],
            'vendorCode',
            'barCodes',
            'category' => [
                'title',
                'fullTitle',
                'slug',
                'breadcrumbs'
            ],
            'slug',
            'urls',
            'prices',
            'defaultImage',
            'images',
            'quantity' => [
                'realQty',
                'step',
                'min'
            ]
        ];
    }
}
