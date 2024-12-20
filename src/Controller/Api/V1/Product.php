<?php

namespace EnjoysCMS\Module\Catalog\Controller\Api\V1;

use DI\Container;
use Doctrine\Common\Collections\Collection;
use EnjoysCMS\Core\AbstractController;
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
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

#[Route('/api/v1/catalog/product', 'api_v1_catalog_product_')]
class Product extends AbstractController
{
    public function __construct(
        private readonly Config $config,
        private readonly UrlGeneratorInterface $urlGenerator,
        Container $container
    ) {
        parent::__construct($container);
    }

    private function getProductSerializationContext(): array
    {
        return [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return match ($object::class) {
                    Category::class => $object->getTitle(),
                    \EnjoysCMS\Module\Catalog\Entity\Product::class => $object->getName(),
                };
            },
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
                        if ($price->getPriceGroup()->getCode() === $this->config->getDefaultPriceGroup()) {
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
        ];
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/{id}', 'get', requirements: ['id' => Requirement::UUID], methods: ['GET'])]
    public function getProduct(\EnjoysCMS\Module\Catalog\Repository\Product $productRepository,): ResponseInterface
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
            encoders: [new JsonEncoder()]
        );
        $product = $productRepository->find($this->request->getAttribute('id'));
        return $this->json(
            $serializer->normalize($product, JsonEncoder::FORMAT, context: $this->getProductSerializationContext() )
        );
    }

    #[Route('s', 'get_products', methods: ['GET'])]
    public function getProducts(): ResponseInterface
    {

    }
}
