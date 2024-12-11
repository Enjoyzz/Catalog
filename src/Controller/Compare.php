<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Controller;

use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\NotSupported;
use Enjoys\Cookie\Cookie;
use Enjoys\Cookie\Exception;
use EnjoysCMS\Core\AbstractController;
use EnjoysCMS\Core\Auth\Identity;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Core\Users\Entity\User;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\CompareList;
use EnjoysCMS\Module\Catalog\Service\Compare\GoodsComparator;
use EnjoysCMS\Module\Catalog\Service\Compare\Result\LineMatrix;
use Psr\Http\Message\ResponseInterface;
use Ramsey\Uuid\Uuid;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;


final class Compare extends PublicController
{

    /**
     * @throws \Exception
     */
    #[Route('catalog/compare/count', 'catalog_compare_count', priority: 3)]
    public function getCountGoodsInCompareList(Identity $identity): ResponseInterface
    {
        $compareList = $this->getCompareList($identity->getUser());
        return $this->json(\count($compareList?->getGoodsIds() ?? []));
    }

    /**
     * @throws \Exception
     */
    #[Route('catalog/compare/remove', 'catalog_compare_remove', priority: 3)]
    public function remove(EntityManagerInterface $em, Identity $identity): ResponseInterface
    {
        $parsedBody = json_decode($this->request->getBody()->getContents());
        $product = $this->getProduct($em, $parsedBody->productId ?? null);
        if ($product === null) {
            return $this->json(null);
        }

        $user = $identity->getUser();
        $compareList = $this->getCompareList($user);

        if ($compareList === null) {
            return $this->json(null);
        }

        $goodsIds = $compareList->getGoodsIds();
        $key = array_search($product->getId(), $goodsIds, true);
        if ($key !== false) {
            unset($goodsIds[$key]);
            $compareList->setGoodsIds($goodsIds);
        }
        $em->flush();
        return $this->json('OK');
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    #[Route('catalog/compare/add', 'catalog_compare_add', priority: 3)]
    public function add(Cookie $cookie, EntityManagerInterface $em, Identity $identity): ResponseInterface
    {
        $parsedBody = json_decode($this->request->getBody()->getContents());

        $product = $this->getProduct($em, $parsedBody->productId ?? null);

        if ($product === null) {
            return $this->json(['error' => true, 'message' => 'Invalid product id'], 400);
        }

        $user = $identity->getUser();

        $compareList = $this->getCompareList($user);

        if ($compareList === null) {
            $compareList = new CompareList();
            $compareList->setId(Uuid::uuid4()->toString());
            if (!$user->isGuest()) {
                $compareList->setUser($user);
            }

            $em->persist($compareList);
        }

        $compareList->setCreatedAt(new \DateTimeImmutable());
        $compareList->setGoodsIds(\array_unique(\array_merge($compareList->getGoodsIds(), [$product->getId()])));

        $cookie->set(
            'compare-list-id',
            $compareList->getId(),
            new \DateTimeImmutable('+7 days')
        );

        $em->flush();

        return $this->json(sprintf('%s добавлен в сравнение', $product->getName()));
    }


    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws DependencyException
     * @throws NotFoundException
     * @throws NotSupported
     * @throws \Exception
     */
    #[Route('catalog/compare', 'catalog_compare', priority: 3)]
    public function compare(GoodsComparator $goodsComparator, Identity $identity, Config $config): ResponseInterface
    {
        // 5%
        $this->gc(50000);

        $this->breadcrumbs->add('catalog/index', 'Каталог')->add(title: 'Сравнение товаров');

        /** @var \EnjoysCMS\Module\Catalog\Repository\Product $repo */
        $repo = $this->container->get(EntityManager::class)->getRepository(
            \EnjoysCMS\Module\Catalog\Entity\Product::class
        );


        $compareList = $this->getCompareList($identity->getUser());

        /** @var \EnjoysCMS\Module\Catalog\Entity\Product[] $products */
        $products = $repo->findByIds($compareList?->getGoodsIds() ?? []);

        $goodsComparator->addProducts($products);


        return $this->response($this->twig->render('@m/catalog/compare.twig', [
            'breadcrumbs' => $this->breadcrumbs,
            'comparator' => $goodsComparator,
            'comparisonGoods' => (new LineMatrix($goodsComparator))->setRemoveRepeat(
                (bool)$this->request->getQueryParams()['remove_repeat_values'] ?? false
            ),
            'config' => $config
        ]));
    }


    private function getCompareList(User $user): ?CompareList
    {
        try {
            $compareListId = $this->request->getCookieParams()['compare-list-id'] ?? '';
            $em = $this->container->get(EntityManagerInterface::class);
            $repository = $em->getRepository(CompareList::class);
            if ($user->isGuest()) {
                /** @var null|CompareList $compareList */
                $compareList = $repository->find($compareListId);
                if ($compareList === null) {
                    return null;
                }
                if (new \DateTimeImmutable('-7 days') > $compareList->getCreatedAt()) {
                    $em->remove($compareList);
                    $em->flush();
                    return null;
                }
                return $compareList;
            }
            return $repository->findOneBy(['user' => $user]);
        } catch (ConversionException) {
            return null;
        }
    }

    private function getProduct(EntityManagerInterface $em, ?string $id): ?\EnjoysCMS\Module\Catalog\Entity\Product
    {
        if ($id === null) {
            return null;
        }
        return $em->getRepository(\EnjoysCMS\Module\Catalog\Entity\Product::class)->find($id);
    }

    /**
     * Garbage Collector
     *
     * @param int $gcProbability gcProbability the probability (parts per million) that garbage
     * collection (GC) should be performed when storing a piece of data in the cache.
     * Defaults to 10, meaning 0.001% chance. This number should be between 0 and 1000000.
     * A value 0 means no GC will be performed at all.
     * @throws DependencyException
     * @throws NotFoundException
     */
    private function gc(int $gcProbability = 10): void
    {
        if (\random_int(0, 1000000) < $gcProbability) {
            $em = $this->container->get(EntityManagerInterface::class);
            $repository = $em->getRepository(CompareList::class);

            $criteria = new Criteria();
            $criteria
                ->where(Criteria::expr()->eq('user', null))
                ->andWhere(
                    Criteria::expr()->lt('createdAt', new \DateTimeImmutable('-7 days'))
                );

            $result = $repository->matching($criteria);

            foreach ($result as $object) {
                $em->remove($object);
            }

            $em->flush();
        }
    }


}
