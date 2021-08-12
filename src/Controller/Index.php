<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ObjectRepository;
use EnjoysCMS\Core\Components\Breadcrumbs\BreadcrumbsInterface;
use EnjoysCMS\Core\Components\Helpers\Setting;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

final class Index
{


    #[Route(
        path: 'catalog',
        name: 'catalog/index',
        options: ['aclComment' => '[public] Просмотр категорий (индекс)']
    )]
    public function view(EntityManager $entityManager, Environment $twig, BreadcrumbsInterface $breadcrumbs): string
    {
        /**
         * @var EntityRepository|ObjectRepository|\EnjoysCMS\Module\Catalog\Repositories\Category $categoryRepository
         */
        $categoryRepository = $entityManager->getRepository(\EnjoysCMS\Module\Catalog\Entities\Category::class);

        $breadcrumbs->add(null, 'Каталог');

        $template_path = '@m/catalog/category_index.twig';
        if (!$twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/category_index.twig';
        }

        return $twig->render(
            $template_path,
            [
                '_title' => sprintf(
                    '%2$s - %1$s',
                    Setting::get('sitename'),
                    'Каталог'
                ),
                'categories' => $categoryRepository->getRootNodes(),
                'breadcrumbs' => $breadcrumbs->get(),
            ]
        );
    }
}