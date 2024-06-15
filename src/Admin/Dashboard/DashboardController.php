<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Dashboard;

use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use Psr\Http\Message\ResponseInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route(
    path: "/admin/catalog",
    name: '@catalog_admin'
)]
final class DashboardController extends AdminController
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function __invoke(): ResponseInterface
    {
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/dashboard.twig'
            )
        );
    }
}
