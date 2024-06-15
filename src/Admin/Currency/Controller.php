<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Currency;


use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Elements\Hidden;
use Enjoys\Forms\Exception\ExceptionRule;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use EnjoysCMS\Module\Catalog\Entity\Currency\Currency;
use Psr\Http\Message\ResponseInterface;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('admin/catalog/currency', '@catalog_currency_')]
final class Controller extends AdminController
{

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(
        name: 'list',
        comment: 'Просмотр списка валют'
    )]
    public function manage(EntityManagerInterface $em): ResponseInterface
    {
        $this->breadcrumbs->setLastBreadcrumb('Список валют', 'catalog/admin/currency');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/currency.twig',
                [
                    'currencies' => $em->getRepository(Currency::class)->findAll(),
                ]
            )
        );
    }

    /**
     * @throws ExceptionRule
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws SyntaxError
     * @throws NotFoundException
     */
    #[Route(
        path: '/add',
        name: 'add',
        comment: 'Добавление валюты'
    )]
    public function add(Add $add): ResponseInterface
    {
        $this->breadcrumbs->add('@catalog_currency_list', 'Список валют')->setLastBreadcrumb('Добавление валюты');

        $form = $add->getForm();

        if ($form->isSubmitted()) {
            $add->doAction();
            exec('php ' . __DIR__ . '/../../../bin/catalog currency-rate-update');
            return $this->redirect->toRoute('@catalog_currency_list');
        }


        $rendererForm = $this->adminConfig->getRendererForm($form);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'title' => 'Добавление Валюты',
                    'subtitle' => '',
                    'form' => $rendererForm
                ]
            )
        );
    }

    /**
     * @throws ExceptionRule
     * @throws ORMException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws OptimisticLockException
     * @throws SyntaxError
     * @throws NotFoundException
     */
    #[Route(
        path: '/edit',
        name: 'edit',
        options: [
            'comment' => '[ADMIN] Редактирование валюты'
        ]
    )]
    public function edit(Edit $edit): ResponseInterface
    {
        $form = $edit->getForm();

        if ($form->isSubmitted()) {
            $edit->doAction();

            exec('php ' . __DIR__ . '/../../../bin/catalog currency-rate-update');

            return $this->redirect->toRoute('@catalog_currency_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->add('@catalog_currency_list', 'Список валют')->setLastBreadcrumb(
            sprintf('Редактирование валюты: %s', $edit->getCurrency()->getName())
        );

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'title' => 'Редактирование Валюты',
                    'subtitle' => $edit->getCurrency()->getName(),
                    'form' => $rendererForm,
                ]
            )
        );
    }


    /**
     * @throws ExceptionRule
     * @throws ORMException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws OptimisticLockException
     * @throws Throwable
     * @throws SyntaxError
     * @throws NotFoundException
     */
    #[Route(
        path: '/delete',
        name: 'delete',
        options: [
            'comment' => '[ADMIN] Удаление валюты'
        ]
    )]
    public function delete(Delete $delete): ResponseInterface
    {
        $form = $delete->getForm();

        /** @var Hidden $rule */
        $rule = $form->getElement('rule');

        if ($form->isSubmitted()) {
            try {
                $delete->doAction();
                return $this->redirect->toRoute('@catalog_currency_list');
            } catch (Throwable $e) {
                if ($e->getCode() !== 1451) {
                    throw $e;
                }
                $rule->setRuleError(
                    'Эта валюта используется в ценах на товары, поэтому удалить нельзя.
                    Сначала нужно удалить все товары с этой валютой'
                );
            }
        }

        if ($rule->isRuleError()) {
            $form->header($rule->getRuleErrorMessage())->addClass('text-danger');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->add('@catalog_currency_list', 'Список валют')->setLastBreadcrumb(
            sprintf('Удаление валюты: %s', $delete->getCurrency()->getName())
        );

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'title' => 'Удаление Валюты',
                    'subtitle' => $delete->getCurrency()->getName(),
                    'form' => $rendererForm,
                ]
            )
        );
    }
}
