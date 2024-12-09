<?php

namespace EnjoysCMS\Module\Catalog\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Repository\WishlistRepository;
use Exception;
use Psr\Http\Message\ResponseInterface;

#[Route('/catalog/wishlist', name: 'catalog_wishlist_', priority: 3)]
class Wishlist extends AbstractController
{

    #[Route(name: 'view')]
    public function view(WishlistRepository $wishlistRepository, EntityManagerInterface $em, Identity $identity): ResponseInterface
    {
        $wishlist = $wishlistRepository->findBy(['user' => $identity->getUser()]);
        dd($wishlist);
        return $this->json(json_encode($wishlist));
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
    public function addOrRemove(EntityManagerInterface $em, WishlistRepository $wishlistRepository, \EnjoysCMS\Module\Catalog\Repository\Product $productRepository, Identity $identity): ResponseInterface
    {
        $user = $identity->getUser();

        if ($user->isGuest()) {
            return $this->json(['error' => true, 'message' => 'Для добавления товара в избранное - необходимо авторизоваться'], 400);
        }

        $parsedBody = json_decode($this->request->getBody()->getContents());

        try {
            $product = $productRepository->find($parsedBody->productId ?? throw new \InvalidArgumentException());
        }catch (\InvalidArgumentException) {
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
        $wishlistProduct->setCreatedAt(new \DateTimeImmutable());
        $wishlistProduct->setUser($user);
        $wishlistProduct->setProduct($product);
        $wishlistProduct->setProductOptions([]);

        $em->persist($wishlistProduct);
        $em->flush();

        return $this->json(sprintf('%s добавлен в избранное', $product->getName()));

    }

}
