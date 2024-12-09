<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\ProductGroup;


use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Exception\ExceptionRule;
use EnjoysCMS\Core\ContentEditor\ContentEditor;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\ProductGroup;
use EnjoysCMS\Module\Catalog\Repository\ProductGroupRepository;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * TODO
 */
#[Route('admin/catalog/product_group', '@catalog_product_group_')]
final class Controller extends AdminController
{
    public function __construct(
        Container $container,
        Config $config,
        \EnjoysCMS\Module\Admin\Config $adminConfig,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct($container, $config, $adminConfig);
    }

    /**
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws LoaderError
     */
    #[Route(
        path: 's',
        name: 'list',
        comment: 'Просмотр групп товаров'
    )]
    public function list(): ResponseInterface
    {
        $repository = $this->em->getRepository(ProductGroup::class);
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/group/list.twig',
                [
                    'productGroups' => $repository->findAll(),
                ]
            )
        );
    }

    #[Route(
        path: '/add',
        name: 'add',
        comment: 'Добавить новую группу товаров (объединение карточек)'
    )]
    public function add(): ResponseInterface
    {
        return $this->response('');
    }

    /**
     * @throws ExceptionRule
     * @throws ORMException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws OptimisticLockException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/edit/{group_id}',
        name: 'edit',
        requirements: [
            'group_id' => Requirement::UUID
        ],
        comment: 'Редактировать группу товаров (объединение карточек)'
    )]
    public function edit(CreateUpdateProductGroupForm $createUpdateProductGroupForm, ProductGroupRepository $productGroupRepository): ResponseInterface
    {
        $productGroup = $productGroupRepository->find(
            $this->request->getAttribute('group_id')
            ?? throw new \InvalidArgumentException(
            '`group_id` param is invalid or not exists'
        )
        ) ?? throw new NoResultException();

        $form = $createUpdateProductGroupForm->getForm($productGroup);

        if ($form->isSubmitted()) {
            $createUpdateProductGroupForm->doAction($productGroup);
            return $this->redirect->toRoute('@catalog_product_group_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/group/form.twig', [
                    'form' => $rendererForm,
                    'productGroup' => $productGroup,
                    'title' => sprintf("Редактирование группы товаров: %s", $productGroup->getTitle())
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/advanced_options/edit/{group_id}',
        name: 'advanced_options',
        requirements: [
            'group_id' => Requirement::UUID
        ],
        comment: 'Расширенное редактирование опций (объединение карточек)'
    )]
    public function advancedOptionsEdit(
        AdvancedGroupOptionForm $advancedGroupOptionForm,
        ContentEditor $contentEditor
    ): ResponseInterface {
        $repository = $this->em->getRepository(ProductGroup::class);
        $productGroup = $repository->find(
            $this->request->getAttribute('group_id')
            ?? throw new \InvalidArgumentException(
            '`group_id` param is invalid or not exists'
        )
        ) ?? throw new NoResultException();

        $productGroupOptions = $productGroup->getOptions()->toArray();
        $form = $advancedGroupOptionForm->getForm($productGroupOptions);

        if ($form->isSubmitted()) {
            $advancedGroupOptionForm->doAction($productGroupOptions);
            return $this->redirect->toRoute('@catalog_product_group_edit', ['group_id' => $productGroup->getId()]);
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/group/form.twig', [
                    'form' => $rendererForm,
                    'title' => sprintf(
                        "Расширенная настройка параметров для группы товаров: %s",
                        $productGroup->getTitle()
                    ),
                    'productGroup' => $productGroup,
                    'editorEmbedCode' => $contentEditor->withConfig(
                        $this->config->getEditorConfigAdvancedGroupOptionEdit()
                    )->setSelector(".extra-textarea")->getEmbedCode()
                ]
            )
        );
    }
}
