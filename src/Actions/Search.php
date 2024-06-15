<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Actions;


use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Core\Pagination\Pagination;
use EnjoysCMS\Core\Setting\Setting;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Repository;
use Psr\Http\Message\ServerRequestInterface;

final class Search
{

    private string $searchQuery;
    private array $optionKeys = [];


    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly Repository\Product $productRepository,
        private readonly Setting $setting,
        private readonly Config $config
    ) {
        $this->searchQuery = \trim($this->request->getQueryParams()['q'] ?? $this->request->getParsedBody()['q'] ?? '');
    }

    /**
     * @throws NotFoundException
     */
    public function getSearchResult(array $optionKeys = []): array
    {
        $this->validateSearchQuery();

        $pagination = new Pagination($this->request->getQueryParams()['page'] ?? 1, $this->config->get('limitItems'));

        $qb = $this->getFoundProducts($optionKeys);
        $qb->setFirstResult($pagination->getOffset())->setMaxResults($pagination->getLimitItems());
        $result = new Paginator($qb);
        $pagination->setTotalItems($result->count());

        return [
            'searchQuery' => $this->searchQuery,
            'countResult' => $result->count(),
            'optionKeys' => $this->optionKeys,
            'pagination' => $pagination,
            'products' => $result,
            '_title' => sprintf(
                'Поиск: %2$s #страница %3$d - Найдено: %4$d - %1$s',
                $this->setting->get('sitename'),
                $this->searchQuery,
                $pagination->getCurrentPage(),
                $result->count()
            ),
        ];
    }


    private function getFoundProducts(array $optionKeys = []): QueryBuilder
    {
        $this->optionKeys = $optionKeys;
        $qb = $this->productRepository->createQueryBuilder('p');

        $qb->select('p', 'm', 'u', 'ov')
            ->leftJoin('p.meta', 'm')
            ->leftJoin('p.urls', 'u')
            ->leftJoin('p.category', 'c')
            ->leftJoin('p.options', 'ov', Expr\Join::WITH, 'ov.optionKey IN (:key) ')
            ->where('p.name LIKE :option')
            ->orWhere('p.description LIKE :option')
            ->orWhere('c.title LIKE :option')
            ->orWhere('ov.value LIKE :option')
            ->andWhere('p.active = true')
            ->andWhere('c.status = true')
            ->setParameters([
                'key' => $optionKeys,
                'option' => '%' . $this->searchQuery . '%'
            ])//
        ;


        return $qb;
    }

    private function validateSearchQuery(): void
    {
        if (mb_strlen($this->searchQuery) < $this->config->get('minSearchChars', 3)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Слишком короткое слово для поиска (нужно минимум %s символа)',
                    $this->config->get('minSearchChars', 3)
                )
            );
        }
    }


}
