<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\QueryException;
use Doctrine\Persistence\ObjectRepository;
use EnjoysCMS\Core\Breadcrumbs\BreadcrumbCollection;
use Invoker\InvokerInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class Index extends PublicController
{


    /**
     * @throws SyntaxError
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws NoResultException
     */
    #[Route(
        path: 'catalog',
        name: 'catalog/index',
        options: ['comment' => '[PUBLIC] Просмотр категорий (индекс)'],
        priority: 2
    )]
    public function view(
        EntityManager $entityManager,
        BreadcrumbCollection $breadcrumbs,
        InvokerInterface $invoker
    ): ResponseInterface {
        /**
         * @var EntityRepository|ObjectRepository|\EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository
         */
        $categoryRepository = $entityManager->getRepository(\EnjoysCMS\Module\Catalog\Entity\Category::class);

        $breadcrumbs->setLastBreadcrumb('Каталог');

        $template_path = '@m/catalog/category_index.twig';
        if (!$this->twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/category_index.twig';
        }

        $categories = $categoryRepository->getChildNodes(null, ['status' => true]);

        return $this->response(
            $this->twig->render(
                $template_path,
                [
                    'meta' => [
                        'title' => $invoker->call(
                            $this->config->get('indexMetaTitleCallback') ?? function () {
                            return sprintf(
                                '%2$s - %1$s',
                                $this->setting->get('sitename'),
                                'Каталог'
                            );
                        }, ['categories' => $categories]
                        ),
                        'keywords' => $invoker->call(
                            $this->config->get('indexMetaKeywordsCallback') ?? function () {
                            return null;
                        }, ['categories' => $categories]
                        ),
                        'description' => $invoker->call(
                            $this->config->get('indexMetaDescriptionCallback') ?? function () {
                            return null;
                        }, ['categories' => $categories]
                        ),
                    ],
                    'categories' => $categories,
                    'categoryRepository' => $categoryRepository,
                    'breadcrumbs' => $breadcrumbs,
                ]
            )
        );
    }
}
