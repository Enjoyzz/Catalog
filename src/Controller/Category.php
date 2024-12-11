<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;

use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use EnjoysCMS\Module\Catalog\Models\CategoryModel;
use Invoker\InvokerInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class Category extends PublicController
{

    /**
     * @throws DependencyException
     * @throws LoaderError
     * @throws NotFoundException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \EnjoysCMS\Core\Exception\NotFoundException
     */
    #[Route(
        path: 'catalog/{slug}@{page}',
        name: 'catalog/category',
        requirements: [
            'slug' => '[^.^@]*',
            'page' => '\d+'
        ],
        options: [
            'comment' => '[public] Просмотр категорий'
        ],
        defaults: [
            'page' => 1,
            'slug' => ''
        ],
        priority: 1
    )]
    public function view(InvokerInterface $invoker, EntityManager $em, CategoryModel $categoryModel): ResponseInterface
    {
        $template_path = '@m/catalog/category.twig';


        if (!$this->twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/category.twig';
        }

        /** @var \EnjoysCMS\Module\Catalog\Entity\Category $category */
        $category = $em
            ->getRepository(\EnjoysCMS\Module\Catalog\Entity\Category::class)
            ->findByPath(
                $this->request->getAttribute('slug', '')
            ) ?? throw new \EnjoysCMS\Core\Exception\NotFoundException(
            sprintf('Not found by slug: %s', $this->request->getAttribute('slug', ''))
        );

        return $this->response(
            $this->twig->render(
                $category->getCustomTemplatePath() ?: $template_path,
                $categoryModel->getContext()
            )
        );
    }

}
