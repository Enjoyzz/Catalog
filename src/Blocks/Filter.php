<?php

namespace EnjoysCMS\Module\Catalog\Blocks;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\RendererInterface;
use EnjoysCMS\Core\Components\Blocks\AbstractBlock;
use EnjoysCMS\Core\Entities\Block as Entity;
use EnjoysCMS\Module\Catalog\Entities\Category;
use EnjoysCMS\Module\Catalog\Entities\OptionValue;
use EnjoysCMS\Module\Catalog\Entities\Product;
use EnjoysCMS\Module\Catalog\Repositories\FilterRepository;
use Psr\Http\Message\ServerRequestInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class Filter extends AbstractBlock
{

    private Environment $twig;

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(private Container $container, Entity $block)
    {
        parent::__construct($block);
        $this->twig = $this->container->get(Environment::class);
    }

    public static function getBlockDefinitionFile(): string
    {
        return __DIR__ . '/../../blocks.yml';
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function view(): string
    {
        $em = $this->container->get(EntityManager::class);
        /** @var \EnjoysCMS\Module\Catalog\Repositories\Category|EntityRepository $categoryRepository */
        $categoryRepository = $em->getRepository(Category::class);
        /** @var EntityRepository|\EnjoysCMS\Module\Catalog\Repositories\Product $productRepository */
        $productRepository = $em->getRepository(Product::class);
        /** @var EntityRepository|FilterRepository $filterRepository */
        $filterRepository = $em->getRepository(\EnjoysCMS\Module\Catalog\Entities\Filter::class);
        $request = $this->container->get(ServerRequestInterface::class);
        $slug = $request->getAttribute('slug', '');
        $category = $categoryRepository->findByPath($slug);
        /** @var \EnjoysCMS\Module\Catalog\Entities\Filter[] $allowedFilters */
        $allowedFilters = $filterRepository->findBy(['category' => $category]);

        /** @var int[] $pids id всех продуктов в этой категории */
        $pids = array_column(
            $em->createQueryBuilder()
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

//        $filters = new \SplObjectStorage();
//        foreach ($allowedFilters as $filter) {
//            $filters[$filter] = $filterRepository->getValues($filter->getOptionKey(), $pids);
//        }

        $form = new Form('get');
        foreach ($allowedFilters as $filter) {
            $form->checkbox('filter[]', $filter->getOptionKey()->__toString())
                ->fill(function () use ($filter, $filterRepository, $pids) {
                    $result = [];
                    /** @var OptionValue $value */
                    foreach ($filterRepository->getValues($filter->getOptionKey(), $pids) as $value) {
                        $result[$value->getId()] = $value->getValue();
                    }
                    return $result;
                });
        }



        $form->submit('submit1', 'Показать')->removeAttribute('name');

        $renderForm = $this->container->get(RendererInterface::class);
        $renderForm->setForm($form);

        return $this->twig->render(
            empty($this->getOption('template')) ? '../modules/catalog/template/blocks/filter.twig' : $this->getOption(
                'template'
            ),
            [
//                'filters' => $filters,
                'form' => $renderForm->output(),
                'blockOptions' => $this->getOptions()
            ]
        );
    }
}
