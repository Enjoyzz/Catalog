<?php

namespace EnjoysCMS\Module\Catalog\Blocks;

use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Renderer\Renderer;
use EnjoysCMS\Core\Block\AbstractBlock;
use EnjoysCMS\Core\Block\Annotation\Block;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\CategoryFilter;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Repository\FilterRepository;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterFactory;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Block(
    name: 'Фильтр товаров',
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
class FilterBlock extends AbstractBlock
{

    public function __construct(
        private readonly Environment $twig,
        private readonly ServerRequestInterface $request,
        private readonly EntityManager $em,
        private readonly FilterFactory $filterFactory,
        private readonly Renderer $renderForm,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @throws LoaderError
     * @throws NoResultException
     * @throws NonUniqueResultException
     * @throws NotSupported
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ExceptionRule
     */
    public function view(): string
    {
        /** @var \EnjoysCMS\Module\Catalog\Repository\Category|EntityRepository $categoryRepository */
        $categoryRepository = $this->em->getRepository(Category::class);
        /** @var Category $category */
        $category = $categoryRepository->findByPath($this->request->getAttribute('slug', ''));

        /** @var EntityRepository|FilterRepository $filterRepository */
        $filterRepository = $this->em->getRepository(CategoryFilter::class);

        /** @var CategoryFilter[] $allowedFilters */
        $allowedFilters = $filterRepository->findBy(['category' => $category], ['order' => 'asc']);

        /** @var int[] $productIds id всех продуктов в этой категории */
        $productIds = array_column(
            $this->em->createQueryBuilder()
                ->select('p.id')
                ->from(Product::class, 'p')
                ->leftJoin('p.category', 'c')
                ->where('p.category IN (:category)')
                ->distinct()
                ->setParameter('category', $categoryRepository->getAllIds($category))
                ->getQuery()
                ->getArrayResult(),
            'id'
        );

        $form = new Form(
            method: 'GET',
            action: $this->urlGenerator->generate('catalog/category', [
                'slug' => $this->request->getAttribute('slug'),
            ])
        );

        $form->setDefaults(['filter' => $this->request->getQueryParams()['filter'] ?? []]);

        $hasFilters = false;

        foreach ($allowedFilters as $filterMetaData) {
            $filter = $this->filterFactory->create($filterMetaData->getFilterType(), $filterMetaData->getParams());

            if ($filter === null) {
                continue;
            }

            $values = $filter->getPossibleValues($productIds);
            if ($values === []) {
                continue;
            }
            $form = $filter->getFormElement($form, $values);
            $hasFilters = true;
        }

        $form->submit('submit1', 'Показать')->removeAttribute('name');


        $this->renderForm->setForm($form);

        $template = empty($this->getBlockOptions()->getValue('template'))
            ? '../modules/catalog/template/blocks/filter.twig' : $this->getBlockOptions()->getValue('template');

        return $this->twig->render(
            $template,
            [
//                'filters' => $filters,
                'form' => $hasFilters ? $this->renderForm->output() : null,
                'blockOptions' => $this->getBlockOptions()
            ]
        );
    }
}
