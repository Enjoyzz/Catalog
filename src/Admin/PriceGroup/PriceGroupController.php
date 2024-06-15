<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\PriceGroup;


use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NoResultException;
use Enjoys\Forms\Exception\ExceptionRule;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\PriceGroup;
use Psr\Http\Message\ResponseInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('admin/catalog/pricegroup', '@catalog_pricegroup_')]
final class PriceGroupController extends AdminController
{

    public function __construct(Container $container, Config $config, \EnjoysCMS\Module\Admin\Config $adminConfig)
    {
        parent::__construct($container, $config, $adminConfig);

        $this->breadcrumbs->add('@catalog_pricegroup_list', 'Группы цен');
    }


    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    #[Route(
        name: 'list'
    )]
    public function list(EntityManagerInterface $em): ResponseInterface
    {
        $this->breadcrumbs->setLastBreadcrumb('Группы цен');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/price_group/price_group_list.twig',
                [
                    'priceGroups' => $em->getRepository(PriceGroup::class)->findAll(),
                ]
            )
        );
    }

    /**
     * @throws SyntaxError
     * @throws ExceptionRule
     * @throws NotFoundException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     */
    #[Route(
        path: '/add',
        name: 'add'
    )]
    public function add(CreateUpdatePriceGroupForm $createUpdatePriceGroupForm): ResponseInterface
    {
        $form = $createUpdatePriceGroupForm->getForm();
        if ($form->isSubmitted()) {
            $createUpdatePriceGroupForm->doAction();
            return $this->redirect->toRoute('@catalog_pricegroup_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->setLastBreadcrumb('Добавление новой группы цен');

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig', [
                    'form' => $rendererForm,
                    'title' => 'Добавление новой группы цен'
                ]
            )
        );
    }


    /**
     * @throws ExceptionRule
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NotSupported
     * @throws NoResultException
     */
    #[Route(
        path: '/edit',
        name: 'edit'
    )]
    public function edit(CreateUpdatePriceGroupForm $createUpdatePriceGroupForm, EntityManager $em): ResponseInterface
    {
        $priceGroup = $em->getRepository(PriceGroup::class)->find(
            $this->request->getQueryParams()['id'] ?? null
        ) ?? throw new NoResultException();

        $form = $createUpdatePriceGroupForm->getForm($priceGroup);
        if ($form->isSubmitted()) {
            $createUpdatePriceGroupForm->doAction($priceGroup);
            return $this->redirect->toRoute('@catalog_pricegroup_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->setLastBreadcrumb('Редактирование группы цен');

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig', [
                    'form' => $rendererForm,
                    'title' => sprintf("Редактирование группы цен: %s", $priceGroup->getTitle())
                ]
            )
        );
    }

    /**
     * @throws NoResultException
     * @throws NotSupported
     * @throws DependencyException
     * @throws NotFoundException
     * @throws ORMException
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    #[Route(
        path: '/delete',
        name: 'delete'
    )]
    public function delete(DeletePriceGroupForm $deletePriceGroupForm, EntityManager $em): ResponseInterface
    {
        $priceGroup = $em->getRepository(PriceGroup::class)->find(
            $this->request->getQueryParams()['id'] ?? null
        ) ?? throw new NoResultException();

        $form = $deletePriceGroupForm->getForm($priceGroup);

        if ($form->isSubmitted()) {
            $deletePriceGroupForm->doAction($priceGroup);
            return $this->redirect->toRoute('@catalog_pricegroup_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->setLastBreadcrumb('Удаление группы цен');

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig', [
                    'form' => $rendererForm,
                    'title' => sprintf("Удаление группы цен: %s", $priceGroup->getTitle())
                ]
            )
        );
    }
}
