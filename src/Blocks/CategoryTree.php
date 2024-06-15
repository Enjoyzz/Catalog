<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Blocks;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\QueryException;
use EnjoysCMS\Core\Block\AbstractBlock;
use EnjoysCMS\Core\Block\Annotation\Block;
use EnjoysCMS\Module\Catalog\Repository\Category;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Block(
    name: 'Категории (tree)',
    options: [
        'template' => [
            'value' => '',
            'name' => 'Путь до template',
            'description' => 'Обязательно',
        ],
        'title' => [
            'value' => '',
            'name' => 'Заголовок блока',
            'description' => 'Для отображения в шаблоне (необязательно)',
        ],
        'description' => [
            'value' => '',
            'name' => 'Небольшое описание блока',
            'description' => 'Для отображения в шаблоне (необязательно)',
        ],
    ]
)]
final class CategoryTree extends AbstractBlock
{


    public function __construct(
        EntityManager $em,
        private readonly Category $categoryRepository,
        private readonly Environment $twig,
        private readonly ServerRequestInterface $request
    ) {
    }


    /**
     * @throws SyntaxError
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws NoResultException
     */
    public function view(): string
    {
        return $this->twig->render(
            $this->getBlockOptions()->getValue('template'),
            [
                'tree' => $this->categoryRepository->getChildNodes(null, ['status' => true]),
                'blockOptions' => $this->getBlockOptions(),
                'currentSlug' => $this->request->getAttribute('slug')
            ]
        );
    }

}
