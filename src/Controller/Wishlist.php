<?php

namespace EnjoysCMS\Module\Catalog\Controller;

use Countable;
use DateTimeImmutable;
use DI\Container;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Core\Pagination\Pagination;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\ORM\Doctrine\Functions\ConvertPrice;
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

    public function __construct(Container $container)
    {
        parent::__construct($container);
        $mode = $this->request->getQueryParams()['sort'] ?? $this->request->getParsedBody()['sort'] ?? null;
        if ($mode !== null) {
            $this->config->setWishlistMode($mode);
        }
        $perpage = $this->request->getQueryParams()['perpage'] ?? $this->request->getParsedBody()['perpage'] ?? null;
        if ($perpage !== null) {
            $this->config->setWishlistPerPage($perpage);
        }
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws NotFoundException
     */
    #[Route(path: '@{page}', name: 'view', requirements: ['page' => '\d+'], defaults: ['page' => 1])]
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
            $this->config->getWishlistPerPage()
        );

        $qb = $wishlistRepository->getAllProductsQueryBuilder($identity->getUser());

        $qb->getEntityManager()->getConfiguration()->addCustomStringFunction('CONVERT_PRICE', ConvertPrice::class);

        if (!in_array($qb->getEntityManager()->getConnection()->getDatabasePlatform()::class, [SqlitePlatform::class])) {
            $qb->addSelect('CONVERT_PRICE(pr.price, pr.currency, :current_currency) as HIDDEN converted_price');
            $qb->setParameter('current_currency', $this->config->getCurrentCurrencyCode());
        } else {
            $qb->addSelect('pr.price as HIDDEN converted_price');
        }

        match ($this->config->getWishlistSortMode()) {
            'price.desc' => $qb->addOrderBy('converted_price', 'DESC'),
            'price.asc' => $qb->addOrderBy('converted_price', 'ASC'),
            'name.desc' => $qb->addOrderBy('p.name', 'DESC'),
            'added.asc' => $qb->addOrderBy('w.createdAt', 'ASC'),
             default => $qb->addOrderBy('w.createdAt', 'DESC'),
        };

        $qb->setFirstResult($pagination->getOffset())
            ->setMaxResults(
                $pagination->getLimitItems()
            );

        /** @var Iterator<\EnjoysCMS\Module\Catalog\Entity\Wishlist>&Countable $result */
        $result = new Paginator($qb->getQuery());
        $pagination->setTotalItems($result->count());

        $this->breadcrumbs->add('catalog/index', 'Каталог');
        $this->breadcrumbs->add('catalog_wishlist_view','Избранное');

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
