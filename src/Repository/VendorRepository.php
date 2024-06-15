<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Repository;

use Doctrine\ORM\EntityRepository;
use EnjoysCMS\Module\Catalog\Entity\Vendor;

/**
 * @method Vendor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Vendor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Vendor[] findAll()
 * @method Vendor[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VendorRepository extends EntityRepository
{
}
