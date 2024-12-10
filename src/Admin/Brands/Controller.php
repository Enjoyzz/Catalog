<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Brands;


use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use EnjoysCMS\Module\Catalog\Repository\VendorRepository;
use Psr\Http\Message\ResponseInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * TODO
 */
#[Route('admin/catalog/brand', '@catalog_brand_')]
final class Controller extends AdminController
{
    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(
        path: 's',
        name: 'list',
        comment: 'Список брендов'
    )]
    public function list(VendorRepository $vendorRepository): ResponseInterface
    {
        return $this->response(
            $this->twig->render($this->templatePath . '/brands.twig', [
                'brands' => $vendorRepository->findAll()
            ])
        );
    }


}
