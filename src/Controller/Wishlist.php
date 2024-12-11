<?php

namespace EnjoysCMS\Module\Catalog\Controller;

use Countable;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Core\Pagination\Pagination;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Repository\WishlistRepository;
use Exception;
use InvalidArgumentException;
use Iterator;
use Psr\Http\Message\ResponseInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('/catalog/wishlist', name: 'catalog_wishlist_', priority: 3)]
class Wishlist extends PublicController
{

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws NotFoundException
     */
    #[Route(path: '@{page}', name: 'view', requirements: ['page' => '\d+'], defaults: ['page' => 1], methods: ['GET'])]
    public function view(
        WishlistRepository $wishlistRepository,
        Identity $identity
    ): ResponseInterface {
        $template_path = '@m/catalog/wishlist.twig';
        if (!$this->twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/wishlist.twig';
        }
        $pagination = new Pagination(
            $this->request->getAttribute('page', 1),
            $this->config->getPerPage()
        );

        $qb = $wishlistRepository->getAllProductsQueryBuilder($identity->getUser());
        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults(
                $pagination->getLimitItems()
            );

        /** @var Iterator<\EnjoysCMS\Module\Catalog\Entity\Wishlist>&Countable $result */
        $result = new Paginator($qb->getQuery());
        $pagination->setTotalItems($result->count());


        return $this->response(
            $this->twig->render(
                $template_path,
                [
                    'pagination' => $pagination,
                    'config' => $this->config,
                    'setting' => $this->setting,
                    'breadcrumbs' => $this->breadcrumbs,
                    'wishlists' => $result
                ]
            )
        );
    }

    #[Route(path: '/count', name: 'count')]
    public function count(WishlistRepository $wishlistRepository, Identity $identity): ResponseInterface
    {
        return $this->json($wishlistRepository->count(['user' => $identity->getUser()]));
    }

    /**
     * @throws Exception
     */
    #[Route('/add', name: 'add_or_remove')]
    public function addOrRemove(
        EntityManagerInterface $em,
        WishlistRepository $wishlistRepository,
        \EnjoysCMS\Module\Catalog\Repository\Product $productRepository,
        Identity $identity
    ): ResponseInterface {
        $user = $identity->getUser();

        if ($user->isGuest()) {
            return $this->json(
                ['error' => true, 'message' => 'Для добавления товара в избранное - необходимо авторизоваться'],
                400
            );
        }

        $parsedBody = json_decode($this->request->getBody()->getContents());

        try {
            $product = $productRepository->find($parsedBody->productId ?? throw new InvalidArgumentException());
        } catch (InvalidArgumentException) {
            $product = null;
        }

        if ($product === null) {
            return $this->json(['error' => true, 'message' => 'Invalid product id'], 400);
        }

        $wishlistProduct = $wishlistRepository->findOneBy(['user' => $user, 'product' => $product]);

        if ($wishlistProduct !== null) {
            $em->remove($wishlistProduct);
            $em->flush();
            return $this->json(sprintf('%s удален из избранного', $product->getName()));
        }

        $wishlistProduct = new \EnjoysCMS\Module\Catalog\Entity\Wishlist();
        $wishlistProduct->setCreatedAt(new DateTimeImmutable());
        $wishlistProduct->setUser($user);
        $wishlistProduct->setProduct($product);
        $wishlistProduct->setProductOptions([]);

        $em->persist($wishlistProduct);
        $em->flush();

        return $this->json(sprintf('%s добавлен в избранное', $product->getName()));
    }

}
