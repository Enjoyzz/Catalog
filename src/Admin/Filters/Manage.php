<?php

namespace EnjoysCMS\Module\Catalog\Admin\Filters;

use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route(
    path: 'admin/catalog/filters',
    name: '@catalog_filters',
    comment: 'Управление Фильтрами'
)]
class Manage  extends AdminController
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(UrlGeneratorInterface $urlGenerator): ResponseInterface
    {
        $this->breadcrumbs->setLastBreadcrumb('Фильтры (настройка)');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/filters.twig'
            )
        );
    }
}
