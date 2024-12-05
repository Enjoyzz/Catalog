<?php

namespace EnjoysCMS\Module\Catalog\Repository;

use EnjoysCMS\Module\Catalog\Entity\Wishlist;
use Doctrine\ORM\EntityRepository;

/**
 * @method Wishlist|null find($id, $lockMode = null, $lockVersion = null)
 * @method Wishlist|null findOneBy(array $criteria, array $orderBy = null)
 * @method Wishlist[] findAll()
 * @method Wishlist[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class WishlistRepository extends EntityRepository
{
}
