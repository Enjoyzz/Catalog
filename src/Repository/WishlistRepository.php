<?php

namespace EnjoysCMS\Module\Catalog\Repository;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use EnjoysCMS\Core\Users\Entity\User;
use EnjoysCMS\Module\Catalog\Entity\Wishlist;

/**
 * @method Wishlist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wishlist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wishlist[] findAll()
 * @method Wishlist[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WishlistRepository extends EntityRepository
{
    public function getAllProductsQueryBuilder(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('w')
            ->select('w', 'p', 'c', 't', 'i', 'm', 'u', 'q', 'pr')
            ->leftJoin('w.product', 'p')
            ->leftJoin('p.category', 'c')
            ->leftJoin('c.parent', 't')
            ->leftJoin('p.meta', 'm')
            ->leftJoin('p.urls', 'u')
            ->leftJoin('p.quantity', 'q')
            ->leftJoin('p.prices', 'pr')
            ->leftJoin('p.images', 'i', Join::WITH, 'i.product = p.id')
            ->where('w.user = :user')
            ->setParameter('user', $user);
    }
}
