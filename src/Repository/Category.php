<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Repository;


use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\QueryException;
use Doctrine\ORM\QueryBuilder;
use Gedmo\Exception\InvalidArgumentException;
use Gedmo\Tree\Entity\Repository\ClosureTreeRepository;

/**
 * @method \EnjoysCMS\Module\Catalog\Entity\Category|null findOneBy(array $criteria, array $orderBy = null)
 * @method \EnjoysCMS\Module\Catalog\Entity\Category[] findAll()
 * @method \EnjoysCMS\Module\Catalog\Entity\Category[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class Category extends ClosureTreeRepository
{

    public function find($id, $lockMode = null, $lockVersion = null)
    {
        if (empty($id)) {
            return null;
        }
        return parent::find($id, $lockMode, $lockVersion);
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function findByPath(?string $path)
    {
        $slugs = explode('/', $path);
        $first = array_shift($slugs);
        $alias = 'c';
        $dql = $this->createQueryBuilder($alias);

        $parameters = ['url' => $first];

        $dql->where("{$alias}.parent IS NULL AND {$alias}.url = :url  AND {$alias}.status = true");
        $parentJoin = "{$alias}.id";

        foreach ($slugs as $k => $slug) {
            $alias = $alias . $k;
            //
            $dql->leftJoin(
                \EnjoysCMS\Module\Catalog\Entity\Category::class,
                $alias,
                Expr\Join::WITH,
                "{$alias}.parent = $parentJoin AND {$alias}.url = :url{$k} AND {$alias}.status = true"
            );

            $parameters['url' . $k] = $slug;

            $parentJoin = $alias . '.id';
        }
        //$dql->andWhere("{$alias}.status = true");
        $dql->select($alias);

        $dql->setParameters($parameters);

        $query = $dql->getQuery();

        return $query->getOneOrNullResult();
    }

    /**
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getChildNodes(
        $node = null,
        array $criteria = [],
        string $orderBy = 'sort',
        string $direction = 'asc'
    ) {
        return $this
            ->getChildNodesQuery($node, $criteria, $orderBy, $direction)
//            ->setFetchMode(Category::class, 'children', ClassMetadata::FETCH_EAGER)
            ->getResult();
    }

    /**
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getChildNodesQuery(
        $node = null,
        array $criteria = [],
        string $orderBy = 'sort',
        string $direction = 'asc'
    ): Query {
        return $this->getChildNodesQueryBuilder($node, $criteria, $orderBy, $direction)->getQuery();
    }

    /**
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getChildNodesQueryBuilder(
        $node = null,
        array $criteria = [],
        string $orderBy = 'sort',
        string $direction = 'asc'
    ): QueryBuilder {
        $currentLevel = 0;

        $maxLevel = $this->createQueryBuilder('c')
            ->select('max(c.level)')
            ->getQuery()
            ->getSingleScalarResult();

        $meta = $this->getClassMetadata();
        $config = $this->listener->getConfiguration($this->_em, $meta->name);

        $dql = $this->getQueryBuilder();
        if ($node === null) {
            $dql->select('node')
                ->from($config['useObjectClass'], 'node')
                ->where('node.' . $config['parent'] . ' IS NULL');
        } else {
            $currentLevel = $this->createQueryBuilder('c')
                ->select('c.level')
                ->where('c.id = :node')
                ->setParameter('node', $node)
                ->getQuery()
                ->getSingleScalarResult();

            $dql->select('node')
                ->from($config['useObjectClass'], 'node')
                ->where('node.' . $config['parent'] . ' = :node')
                ->setParameter('node', $node);
        }

        if ($meta->hasField($orderBy) && in_array(strtolower($direction), ['asc', 'desc'])) {
            $dql->orderBy('node.' . $orderBy, $direction);
        } else {
            throw new InvalidArgumentException(
                "Invalid sort options specified: field - {$orderBy}, direction - {$direction}"
            );
        }


        foreach ($criteria as $field => $value) {
            if ($value instanceof Criteria) {
                $dql->addCriteria($value);
                continue;
            }
            $dql->addCriteria(Criteria::create()->where(Criteria::expr()->eq($field, $value)));
        }

        $parentAlias = 'node';
        for ($i = $currentLevel + 2; $i <= $maxLevel + 1; $i++) {
            $condition = "c{$i}.level = $i and c{$i}.parent = {$parentAlias}.id";
            foreach ($criteria as $field => $value) {
                if ($value instanceof Criteria) {
                    /** @var Comparison $expr */
                    $expr = $value->getWhereExpression()->visit(
                        new Query\QueryExpressionVisitor(["c{$i}"])
                    );
                    $condition .= sprintf(
                        ' AND %s %s %s',
                        $expr->getLeftExpr(),
                        $expr->getOperator(),
                        $expr->getRightExpr()
                    );
                    continue;
                }
                $condition .= " AND c{$i}.{$field} = :{$field}";
                // параметры биндятся автоматически, чудеса )
                // $parameters[$field] = $value;
            }

            $dql->addOrderBy("c{$i}.{$orderBy}", $direction);

            $dql->leftJoin(
                "{$parentAlias}.children",
                "c{$i}",
                Expr\Join::WITH,
                $condition
            );
            $dql->addSelect("c{$i}");

            // join category_meta (\EnjoysCMS\Module\Catalog\Entities\CategoryMeta)
            $dql->leftJoin(
                "{$parentAlias}.meta",
                "m{$i}"
            );
            $dql->addSelect("m{$i}");

            $parentAlias = "c{$i}";
        }

        return $dql;
    }

    /**
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getFormFillArray(
        $node = null,
        array $criteria = [],
        string $orderBy = 'sort',
        string $direction = 'asc'
    ): array {
        return $this->_build($this->getChildNodes($node, $criteria, $orderBy, $direction));
    }

    private function _build($tree, $level = 1): array
    {
        $ret = [];

        foreach ($tree as $item) {
            $ret[$item->getId()] = str_repeat("&nbsp;", ($level - 1) * 3) . $item->getTitle();
            if (count($item->getChildren()) > 0) {
                $ret += $this->_build($item->getChildren(), $item->getLevel() + 1);
            }
        }
        return $ret;
    }

    public function getAllIds($node = null): array
    {
        /** @var \EnjoysCMS\Module\Catalog\Entity\Category[] $nodes */
        $nodes = $this->getChildren($node);
        $ids = array_filter(
            array_map(
                function ($node) {
                    if (!$node->isStatus()) {
                        return null;
                    }
                    return $node?->getId();
                },
                $nodes
            )
        );
        $ids[] = $node?->getId();
        return $ids;
    }

    public function getCountProducts($node = null)
    {
        $nodes = $this->getAllIds($node);
        return $this->getEntityManager()->createQueryBuilder()
            ->select('count(p.id)')
            ->from(\EnjoysCMS\Module\Catalog\Entity\Product::class, 'p')
            ->where('p.category IN (:ids)')
            ->setParameter('ids', $nodes)
            ->getQuery()->getSingleScalarResult();
    }

}
