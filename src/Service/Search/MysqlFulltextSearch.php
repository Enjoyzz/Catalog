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

    private \EnjoysCMS\Module\Catalog\Repository\Product|EntityRepository $productRepository;
    private SearchQuery $searchQuery;
    private array $optionKeys = [];


    public function __construct(
        private EntityManager $em
    )
    {
        $this->em->getConfiguration()->addCustomStringFunction('MATCH', MatchAgainst::class);
        $this->productRepository = $this->em->getRepository(Product::class);
    }


    public function setSearchQuery(SearchQuery $searchQuery): void
    {
        $this->searchQuery = $searchQuery;
    }


    /**
     * @throws \Exception
     */
    public function getResult(int $offset, int $limit): SearchResult
    {
        if ($this->searchQuery === null) {
            throw new \InvalidArgumentException('Not set searchQuery (SearchInterface::setSearchQuery())');
        }

        $qb = $this
            ->getFoundProductsQueryBuilder($this->searchQuery, $this->optionKeys)
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $result = new Paginator($qb);

        return new SearchResult(
            $this->searchQuery,
            $result
        );
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
