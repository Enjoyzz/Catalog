<?php

namespace EnjoysCMS\Module\Catalog\Api;

use DI\Container;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\Image;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductPrice;
use EnjoysCMS\Module\Catalog\Entity\Url;
use EnjoysCMS\Module\Catalog\Service\ProductService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\NameConverter\NameConverterInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class ProductController extends AbstractController
{

    /**
     * @var EncoderInterface[]
     */
    private array $encoders;


    public function __construct(
        Container $container,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly EntityManagerInterface $em,
        private readonly Config $config
    ) {
        parent::__construct($container);
        $this->encoders = [new XmlEncoder(), new JsonEncoder()];
    }


    /**
     * @throws QueryException
     * @throws NotSupported
     * @deprecated
     */
    #[Route(
        path: 'admin/catalog/tools/find-products',
        name: '@a/catalog/tools/find-products',
        options: [
            'comment' => '[JSON] Получение списка продукции (поиск)'
        ]
    )]
    public function findProductsByLike(
        EntityManager $em,
        ServerRequestInterface $request
    ): ResponseInterface {
        /** @var \EnjoysCMS\Module\Catalog\Repository\Product $productRepository */
        $productRepository = $em->getRepository(Product::class);

        $searchCriteria = Criteria::create();
        foreach ($this->config->get('admin->searchFields', []) as $field) {
            $searchCriteria->orWhere(Criteria::expr()->contains($field, $request->getQueryParams()['query'] ?? null));
        }

        $qb = $productRepository->getFindAllBuilder();
        $qb->addCriteria($searchCriteria);

        $query = $qb->getQuery()
            ->setFirstResult(0)
            ->setMaxResults(20);

        $result = new Paginator($query);

        return $this->json([
            'items' => array_map(function (Product $item) {
                return [
                    'id' => $item->getId(),
                    'title' => $item->getName(),
                    'category' => $item->getCategory()->getFullTitle(),
                    'sku' => $item->getSku(),
                    'vendor' => $item->getVendor()?->getName(),
                    'vendorCode' => $item->getVendorCode(),
                    'grouped' => $item->isGrouped(),
                ];
            }, iterator_to_array($result)),
            'total_count' => $result->count()
        ]);
    }


    /**
     * @throws ExceptionInterface
     * @throws NotFoundException
     * @throws QueryException
     * @deprecated
     */
    #[Route(
        path: 'admin/catalog/api/get-products',
        name: 'catalog/admin/api/get-products',
        options: [
            'comment' => 'API: получение списка продуктов'
        ],
        methods: [
            'GET'
        ]
    )]
    public function getProducts(ProductService $productsService): ResponseInterface
    {
        $criteria = [];

        $serializer = new Serializer([
            new ObjectNormalizer(
                nameConverter: new class() implements NameConverterInterface {

                    public function normalize(string $propertyName): string
                    {
                        return match ($propertyName) {
                            'prices' => 'price',
                            default => $propertyName
                        };
                    }

                    public function denormalize(string $propertyName): string
                    {
                        return match ($propertyName) {
                            default => $propertyName
                        };
                    }
                }
            )
        ], $this->encoders);

        $limit = (int)($this->request->getQueryParams()['length'] ?? 10);

        $page = ((int)($this->request->getQueryParams()['start'] ?? 0) / $limit) + 1;


        /** @var \EnjoysCMS\Module\Catalog\Repository\Category|EntityRepository $categoryRepository */
        $categoryRepository = $this->em->getRepository(Category::class);


        $categoryId = $this->request->getQueryParams()['categoryId'] ?? '0';
        $categoryCriteria = Criteria::create()
            ->where(
                Criteria::expr()->in(
                    'p.category',
                    $categoryRepository->getAllIds(
                        $categoryRepository->find($categoryId)
                    )
                )
            );

        if ($categoryId === '0') {
            $categoryCriteria->orWhere(Criteria::expr()->eq('p.category', null));
        }


        $criteria[] = $categoryCriteria;

        $search = (empty(
            $this->request->getQueryParams()['search']['value'] ?? null
        )) ? null : $this->request->getQueryParams()['search']['value'];

        if ($search !== null) {
            $searchCriteria = Criteria::create();
            foreach ($this->config->get('admin->searchFields', []) as $field) {
                $searchCriteria->orWhere(Criteria::expr()->contains($field, $search));
            }
            $criteria[] = $searchCriteria;
        }

        $orders = ['p.id' => 'desc'];
        foreach ($this->request->getQueryParams()['order'] ?? [] as $item) {
            $orders[$this->request->getQueryParams()['columns'][$item['column']]['name']] = $item['dir'];
        }

        $products = $productsService->getProducts(
            page: $page,
            limit: $limit,
            criteria: $criteria,
            orders: $orders
        );

        return $this->json([
            'draw' => $this->request->getQueryParams()['draw'] ?? null,
            'recordsTotal' => $products['pagination']->getTotalItems(),
            'recordsFiltered' => $products['pagination']->getTotalItems(),
            'data' => $serializer->normalize($products['products'], JsonEncoder::FORMAT, [
                AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                    return match ($object::class) {
                        Category::class => $object->getTitle(),
                        Product::class => $object->getName(),
                    };
                },
                AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => 1,
                AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
                AbstractNormalizer::ATTRIBUTES => [
                    'id',
                    'name',
                    'sku',
                    'vendor' => [
                        'id',
                        'name'
                    ],
                    'vendorCode',
                    'barCodes',
                    'hide',
                    'active',
                    'category' => [
                        'title',
                        'slug',
                        'breadcrumbs'
                    ],
                    'slug',
                    'urls',
                    'prices',
                    'defaultImage',
                    'images'
                ],
                AbstractNormalizer::CALLBACKS => [
                    'defaultImage' => function (?Image $image) {
                        if ($image === null) {
                            return null;
                        }
                        $storage = $this->config->getImageStorageUpload($image->getStorage());
                        return [
                            'original' => $storage->getUrl(
                                $image->getFilename() . '.' . $image->getExtension()
                            ),
                            'small' => $storage->getUrl(
                                $image->getFilename() . '_small.' . $image->getExtension()
                            ),
                            'large' => $storage->getUrl(
                                $image->getFilename() . '_large.' . $image->getExtension()
                            ),
                        ];
                    },
                    'images' => function (Collection $images) {
                        return array_map(function ($image) {
                            /** @var Image $image */
                            $storage = $this->config->getImageStorageUpload($image->getStorage());
                            return [
                                'original' => $storage->getUrl(
                                    $image->getFilename() . '.' . $image->getExtension()
                                ),
                                'small' => $storage->getUrl(
                                    $image->getFilename() . '_small.' . $image->getExtension()
                                ),
                                'large' => $storage->getUrl(
                                    $image->getFilename() . '_large.' . $image->getExtension()
                                ),
                            ];
                        }, $images->toArray());
                    },
                    'prices' => function (Collection $prices) {
                        foreach ($prices as $price) {
                            /** @var ProductPrice $price */
                            if ($price->getPriceGroup()->getCode() === 'PUBLIC') {
                                return [
                                    'price' => $price->getPrice(),
                                    'currency' => $price->getCurrency()->getCode(),
                                    'format' => $price->format()
                                ];
                            }
                        }
                        return null;
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
            ])
        ]);
    }

    /**
     * @deprecated
     */
    #[\EnjoysCMS\Core\Routing\Annotation\Route(
        path: '/api/v0/catalog/product/{id}',
        name: 'api_v0_catalog_product_get',
        requirements: [
            'id' => Requirement::UUID
        ],
        methods: ['GET']
    )]
    public function getProduct(\EnjoysCMS\Module\Catalog\Repository\Product $productRepository): ResponseInterface
    {
        $serializer = new Serializer([
            new ObjectNormalizer(
                nameConverter: new class() implements NameConverterInterface {

                    public function normalize(string $propertyName): string
                    {
                        return match ($propertyName) {
                            'prices' => 'price',
                            default => $propertyName
                        };
                    }

                    public function denormalize(string $propertyName): string
                    {
                        return match ($propertyName) {
                            default => $propertyName
                        };
                    }
                }
            )
        ], $this->encoders);

        $product = $productRepository->find($this->request->getAttribute('id'));

        return $this->json($serializer->normalize($product, JsonEncoder::FORMAT, [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return match ($object::class) {
                    Category::class => $object->getTitle(),
                    Product::class => $object->getName(),
                };
            },
            AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => 1,
            AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
            AbstractNormalizer::ATTRIBUTES => [
                'id',
                'name',
                'sku',
                'vendor' => [
                    'id',
                    'name'
                ],
                'vendorCode',
                'barCodes',
                'hide',
                'active',
                'category' => [
                    'title',
                    'slug',
                    'breadcrumbs'
                ],
                'slug',
                'urls',
                'prices',
                'defaultImage',
                'images'
            ],
            AbstractNormalizer::CALLBACKS => [
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
                    foreach ($prices as $price) {
                        /** @var ProductPrice $price */
                        if ($price->getPriceGroup()->getCode() === 'PUBLIC') {
                            return [
                                'price' => $price->getPrice(),
                                'currency' => $price->getCurrency()->getCode(),
                                'format' => $price->format()
                            ];
                        }
                    }
                    return null;
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
        ]));
    }
}
