<?php

namespace EnjoysCMS\Module\Catalog\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Repository\WishlistRepository;
use Exception;
use Psr\Http\Message\ResponseInterface;

#[Route('/catalog/wishlist', name: 'wishlist_', priority: 3)]
class Wishlist extends AbstractController
{

    #[Route(name: 'view')]
    public function view(WishlistRepository $wishlistRepository, EntityManagerInterface $em, Identity $identity): ResponseInterface
    {
        $wishlist = $wishlistRepository->findBy(['user' => $identity->getUser()]);
        dd($wishlist);
        return $this->json(json_encode($wishlist));
    }

    /**
     * @throws Exception
     */
    #[Route('/add', name: 'add')]
    public function add(EntityManagerInterface $em, Identity $identity): ResponseInterface
    {
        $user = $identity->getUser();
        $parsedBody = json_decode($this->request->getBody()->getContents());
        $product = $this->getProduct($em, $parsedBody->productId ?? null);
        if ($product === null) {
            return $this->json(['error' => true, 'message' => 'Invalid product id'], 400);
        }
    }

    private function getProduct(EntityManagerInterface $em, ?string $id): ?\EnjoysCMS\Module\Catalog\Entity\Product
    {
        if ($id === null) {
            return null;
        }
        return $em->getRepository(\EnjoysCMS\Module\Catalog\Entity\Product::class)->find($id);
    }
}
