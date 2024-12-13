<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Service\Search;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use DoctrineExtensions\Query\Mysql\MatchAgainst;
use EnjoysCMS\Module\Catalog\Entity\Product;

final class MysqlFulltextSearch implements SearchInterface
{

    private ?string $error = null;

    public function __construct(
        private readonly \EnjoysCMS\Module\Catalog\Repository\Product $productRepository
    )
    {
        $productRepository->getEntityManager()->getConfiguration()->addCustomStringFunction('MATCH', MatchAgainst::class);
    }



    /**
     * @throws \Exception
     */
    public function getResult(SearchQuery $searchQuery, int $offset, int $limit): SearchResult
    {

        $qb = $this
            ->getFoundProductsQueryBuilder($searchQuery->query, $searchQuery->optionKeys)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $products = new Paginator($qb);

        return new SearchResult(
            $searchQuery,
            products: $products
        );
    }

    public function setError(string $error = null): void
    {
        $this->error = $error;
    }

    public function getError(): ?string
    {
        return $this->error;
    }


    private function getFoundProductsQueryBuilder(string $searchQuery, array $optionKeys = []): QueryBuilder
    {
        return $this->productRepository->getFindAllBuilder()
            ->leftJoin('p.options', 'ov', Join::WITH, 'ov.optionKey IN (:key) ')
            ->where('MATCH(p.name, p.description) AGAINST (:option) > 0')
            ->addSelect('MATCH(p.name, p.description) AGAINST (:option) as HIDDEN score')
            ->orWhere('MATCH (c.title) AGAINST (:option) > 0')
            ->orWhere('MATCH (ov.value) AGAINST (:option) > 0')
            ->andWhere('p.active = true')
            ->andWhere('c.status = true OR c IS null')
            ->orderBy('score', 'desc')
            ->setParameters([
                'key' => $optionKeys,
                'option' => '%' . $searchQuery . '%'
            ])//
            ;
    }


}
