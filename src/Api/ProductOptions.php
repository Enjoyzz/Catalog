<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Api;


use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Module\Catalog\Entity\Product;
use JMS\Serializer\SerializerBuilder;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Annotation\Route;

final class ProductOptions extends AbstractController
{

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    #[Route(
        path: 'admin/catalog/get-product-options-by-category',
        name: '@catalog_get_product_options_by_category',
        options: [
            'comment' => '[JSON] Получение опций товара по категории'
        ]
    )]
    public function getProductOptionsKeysByCategory(\EnjoysCMS\Module\Catalog\Entity\Category $category
    ): ResponseInterface {
        $serializer = SerializerBuilder::create()->build();

        $em = $this->container->get(EntityManagerInterface::class);
        /** @var Product[] $products */
        $products = $em->getRepository(Product::class)->findBy(['category' => $category]);
        $result = [];
        foreach ($products as $product) {
            $result = array_merge($result, array_column($product->getOptions(), 'key'));
        }
        return $this->json($serializer->toArray(array_unique($result)));
    }
}
