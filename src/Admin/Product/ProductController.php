<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product;

use DeepCopy\DeepCopy;
use DeepCopy\Filter\Doctrine\DoctrineCollectionFilter;
use DeepCopy\Matcher\PropertyTypeMatcher;
use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\Query\QueryException;
use Enjoys\Forms\Elements\File;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Renderer\Bootstrap4\Bootstrap4Renderer;
use Enjoys\Functions\TwigExtension\ConvertSize;
use EnjoysCMS\Core\ContentEditor\ContentEditor;
use EnjoysCMS\Core\Routing\Annotation\Route;
use EnjoysCMS\Module\Catalog\Admin\AdminController;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\CreateUpdateProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\CreateUpdateUrlProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\DeleteProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\DeleteUrlProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\FileUploadProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\MetaProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\PriceProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\QuantityProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Form\TagsProductForm;
use EnjoysCMS\Module\Catalog\Admin\Product\Images\LoadImage;
use EnjoysCMS\Module\Catalog\Admin\Product\Images\ManageImage;
use EnjoysCMS\Module\Catalog\Admin\Product\Images\UploadHandler;
use EnjoysCMS\Module\Catalog\Admin\Product\Options\ManageOptions;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Image;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductFiles;
use EnjoysCMS\Module\Catalog\Entity\Url;
use EnjoysCMS\Module\Catalog\Events\PostAddProductEvent;
use EnjoysCMS\Module\Catalog\Events\PostDeleteProductEvent;
use EnjoysCMS\Module\Catalog\Events\PostEditProductEvent;
use EnjoysCMS\Module\Catalog\Events\PostUploadFile;
use EnjoysCMS\Module\Catalog\Events\PreAddProductEvent;
use EnjoysCMS\Module\Catalog\Events\PreDeleteProductEvent;
use EnjoysCMS\Module\Catalog\Events\PreEditProductEvent;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Route('admin/catalog/product', '@catalog_product_')]
final class ProductController extends AdminController
{
    private ?Product $product;

    public function __construct(Container $container, Config $config, \EnjoysCMS\Module\Admin\Config $adminConfig)
    {
        parent::__construct($container, $config, $adminConfig);

        $this->product = $container->get(EntityManager::class)->getRepository(Product::class)->find(
            $this->request->getAttribute('product_id') ?? $this->request->getQueryParams()['product_id'] ?? 0
        );

        $this->breadcrumbs->add('@catalog_product_list', 'Список товаров (продуктов)');
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    #[Route(
        path: 's',
        name: 'list',
        comment: 'Просмотр товаров'
    )]
    public function list(): ResponseInterface
    {
        $this->breadcrumbs->remove('@catalog_product_list')->setLastBreadcrumb('Список товаров (продуктов)');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/products.twig'
            )
        );
    }

    /**
     * @throws ExceptionRule
     * @throws ORMException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    #[Route(
        path: '/add',
        name: 'add',
        comment: 'Добавление товара'
    )]
    public function add(CreateUpdateProductForm $add, ContentEditor $contentEditor): ResponseInterface
    {
        $this->breadcrumbs->setLastBreadcrumb('Добавление товара');

        $form = $add->getForm();

        if ($form->isSubmitted()) {
            $this->dispatcher->dispatch(new PreAddProductEvent());
            $product = $add->doAction();
            $this->dispatcher->dispatch(new PostAddProductEvent($product));
            return $this->redirect->toRoute('@catalog_product_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/addproduct.twig',
                [
                    'form' => $rendererForm,
                    'editorEmbedCode' => $contentEditor
                        ->withConfig($this->config->getEditorConfigProductDescription())
                        ->setSelector('#description')
                        ->getEmbedCode()
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
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NotSupported
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/edit',
        name: 'edit',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Редактирование товара'
    )]
    public function edit(CreateUpdateProductForm $edit, ContentEditor $contentEditor): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $this->breadcrumbs->setLastBreadcrumb(
            sprintf('Редактирование общей информации `%s`', $product->getName()),
        );

        $form = $edit->getForm($product);


        if ($form->isSubmitted()) {
            $copier = new DeepCopy();
            $copier->addFilter(
                new DoctrineCollectionFilter(),
                new PropertyTypeMatcher('Doctrine\Common\Collections\Collection')
            );
            /** @var Product $oldProduct */
            $oldProduct = $copier->copy($product);

            $this->dispatcher->dispatch(
                new PreEditProductEvent($oldProduct)
            );

            $edit->doAction($product);

            $this->dispatcher->dispatch(new PostEditProductEvent($oldProduct, $product));

            $this->redirect->toRoute('@catalog_product_list', emit: true);
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);
        $rendererForm->setOptions([
            'custom-switch' => true
        ]);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/editproduct.twig',
                [
                    'form' => $rendererForm,
                    'auto_urlify' => false,
                    'product' => $product,
                    'subtitle' => 'Редактирование',
                    'editorEmbedCode' => $contentEditor
                        ->withConfig($this->config->getEditorConfigProductDescription())
                        ->setSelector('#description')
                        ->getEmbedCode(),
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws FilesystemException
     * @throws LoaderError
     * @throws DependencyException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/delete',
        name: 'delete',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Удаление товара'
    )]
    public function delete(DeleteProductForm $deleteProductForm): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $this->breadcrumbs->setLastBreadcrumb(sprintf('Удаление товара: `%s`', $product->getName()));
        $form = $deleteProductForm->getForm();

        if ($form->isSubmitted()) {
            $this->dispatcher->dispatch(new PreDeleteProductEvent($this->product));
            $deleteProductForm->doAction($product);
            $this->dispatcher->dispatch(new PostDeleteProductEvent($this->product));
            return $this->redirect->toRoute('@catalog_product_list');
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $product,
                    'form' => $rendererForm,
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
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/tags',
        name: 'tags',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Теги товара'
    )]
    public function tags(TagsProductForm $tagsProductForm): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $form = $tagsProductForm->getForm($product);

        $this->breadcrumbs->setLastBreadcrumb(sprintf('Менеджер тегов: %s', $product->getName()));

        if ($form->isSubmitted()) {
            $tagsProductForm->doAction($product);
            return $this->redirect->toUrl();
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/tags.twig',
                [
                    'product' => $product,
                    'subtitle' => 'Управление тегами',
                    'form' => $rendererForm->output(),
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/quantity',
        name: 'quantity',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Установка количества на товар'
    )]
    public function quantity(QuantityProductForm $quantityProductForm): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $form = $quantityProductForm->getForm($product);
        $this->breadcrumbs->setLastBreadcrumb(
            sprintf('Настройка количества: `%s`', $product->getName())
        );

        if ($form->isSubmitted()) {
            $quantityProductForm->doAction($product);

            return $this->redirect->toUrl();
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);


        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $product,
                    'form' => $rendererForm,
                    'subtitle' => 'Установка количества',
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/meta',
        name: 'meta',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Управление Meta-tags (продукт)'
    )]
    public function meta(MetaProductForm $metaProductForm): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $form = $metaProductForm->getForm($product);

        if ($form->isSubmitted()) {
            $metaProductForm->doAction($product);
            return $this->redirect->toUrl();
        }


        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs
            ->add(['@catalog_product_edit', ['product_id' => $product->getId()]], $product->getName())
            ->setLastBreadcrumb(
                sprintf('META-данные: %s', $product->getName())
            );

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $product,
                    'subtitle' => 'Установка META данных HTML',
                    'form' => $rendererForm,
                ]
            )
        );
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/urls',
        name: 'urls',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Просмотр URLs товара'
    )]
    public function urls(): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $this->breadcrumbs
            ->add(['@catalog_product_edit', ['product_id' => $product->getId()]], $product->getName())
            ->setLastBreadcrumb(
                sprintf('Менеджер ссылок: %s', $product->getName())
            );


        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/urls/manage.twig',
                [
                    'product' => $product,
                    'subtitle' => 'URLs',
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws OptimisticLockException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     * @throws ExceptionRule
     */
    #[Route(
        path: '/{product_id}/urls/edit',
        name: 'urls_edit',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Редактирование URL'
    )]
    public function urlsEdit(CreateUpdateUrlProductForm $editUrl): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $routeData = ['@catalog_product_urls', ['product_id' => $this->product->getId()]];

        $form = $editUrl->getForm($product);

        if ($form->isSubmitted()) {
            $editUrl->doAction($product);
            return $this->redirect->toRoute(...$routeData);
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->add($routeData, 'Менеджер ссылок')
            ->setLastBreadcrumb('Редактирование ссылки');

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $this->product,
                    'form' => $rendererForm,
                    'subtitle' => 'Редактирование URL',
                ]
            )
        );
    }

    /**
     * @throws ORMException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws OptimisticLockException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     * @throws ExceptionRule
     */
    #[Route(
        path: '/{product_id}/urls/add',
        name: 'urls_add',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Добавление URL'
    )]
    public function urlsAdd(CreateUpdateUrlProductForm $addUrl): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $routeData = ['@catalog_product_urls', ['product_id' => $this->product->getId()]];
        $form = $addUrl->getForm($product);

        if ($form->isSubmitted()) {
            $addUrl->doAction($product);
            return $this->redirect->toRoute(...$routeData);
        }
        $rendererForm = $this->adminConfig->getRendererForm($form);
        $this->breadcrumbs->add($routeData, 'Менеджер ссылок')
            ->setLastBreadcrumb('Добавление ссылки');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $this->product,
                    'form' => $rendererForm,
                    'subtitle' => 'Добавление URL',
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
        path: '/{product_id}/urls/delete',
        name: 'urls_delete',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Удаление URL'
    )]
    public function urlsDelete(DeleteUrlProductForm $deleteUrl): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $routeData = ['@catalog_product_urls', ['product_id' => $this->product->getId()]];

        $form = $deleteUrl->getForm($product);

        if ($form->isSubmitted()) {
            $deleteUrl->doAction($product);
            return $this->redirect->toRoute(...$routeData);
        }
        $rendererForm = $this->adminConfig->getRendererForm($form);
        $this->breadcrumbs->add($routeData, 'Менеджер ссылок')
            ->setLastBreadcrumb('Удаление ссылки');
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/urls/delete.twig',
                [
                    'product' => $this->product,
                    'form' => $rendererForm->output(),
                    'subtitle' => 'Удаление URL',
                ]
            )
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws NoResultException
     * @throws ORMException
     */
    #[Route(
        path: '/{product_id}/urls/makedefault',
        name: 'urls_make_default',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Сделать URL основным'
    )]
    public function urlsMakeDefault(EntityManager $em): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $routeData = ['@catalog_product_urls', ['product_id' => $this->product->getId()]];


        $setFlag = false;
        /** @var Url $url */
        foreach ($product->getUrls() as $url) {
            if ($url->getId() === (int)($this->request->getQueryParams()['url_id'] ?? null)) {
                $url->setDefault(true);
                $setFlag = true;
                continue;
            }
            $url->setDefault(false);
        }

        if ($setFlag === false) {
            throw new InvalidArgumentException('Url id is invalid');
        }

        $em->flush();

        return $this->redirect->toRoute(...$routeData);
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws NotSupported
     * @throws LoaderError
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/images',
        name: 'images',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Управление изображениями товара'
    )]
    public function images(EntityManager $em): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $this->breadcrumbs
            ->add(['@catalog_product_edit', ['product_id' => $product->getId()]], $product->getName())
            ->setLastBreadcrumb(
                sprintf('Менеджер изображений: `%s`', $product->getName())
            );

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/images/manage.twig',
                [
                    'product' => $product,
                    'images' => $em->getRepository(Image::class)->findBy(['product' => $this->product])
                ]
            )
        );
    }

    /**
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/images/add',
        name: 'images_add',
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Загрузка изображения к товару'
    )]
    public function imagesAdd(EntityManager $em): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();
        $method = $this->request->getQueryParams()['method'] ?? 'upload';

        $this->breadcrumbs
            ->add(['@catalog_product_edit', ['product_id' => $product->getId()]], $product->getName())
            ->setLastBreadcrumb(
                sprintf('Добавление нового изображения: `%s`', $product->getName())
            );

        if (!in_array($method, ['upload', 'download'], true)) {
            $method = 'upload';
        }

        /** @var class-string<LoadImage> $method */
        $method = 'EnjoysCMS\Module\Catalog\Admin\Product\Images\\' . ucfirst($method);

        $uploadMethod = $this->container->make($method);

        $form = $uploadMethod->getForm();

        $rendererForm = $this->adminConfig->getRendererForm($form);

        if ($form->isSubmitted()) {
            try {
                foreach ($uploadMethod->upload($this->request) as $item) {
                    $manageImage = new ManageImage($product, $em, $this->config);
                    $manageImage->addToDB(
                        $item->getName(),
                        $item->getExtension()
                    );
                }

                return $this->redirect->toRoute(
                    '@catalog_product_images',
                    ['product_id' => $product->getId()]
                );
            } catch (Throwable $e) {
                /** @var File $image */
                $image = $form->getElement('image');
                $image->setRuleError(htmlspecialchars(sprintf('%s: %s', $e::class, $e->getMessage())));
            }
        }

        return $this->response(
            $this->twig->render(
                $uploadMethod->getTemplatePath($this->templatePath),
                [
                    'form' => $rendererForm,
                    'product' => $product,
                    'subtitle' => 'Загрузка изображения для продукта',
                ]
            )
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotFoundException
     * @throws NotSupported
     */
    #[Route(
        path: '/images/make_general',
        name: 'images_make_general',
        options: [
            'comment' => 'Переключение основного изображения'
        ]
    )]
    public function imagesMakeGeneral(
        EntityManager $em
    ): ResponseInterface {
        $repository = $em->getRepository(Image::class);
        $image = $repository->find($this->request->getQueryParams()['id'] ?? null) ?? throw new NotFoundException(
            sprintf('Not found by id: %s', $this->request->getQueryParams()['id'] ?? null)
        );

        $images = $repository->findBy(['product' => $image->getProduct()]);
        foreach ($images as $item) {
            $item->setGeneral(false);
        }
        $image->setGeneral(true);
        $em->flush();

        return $this->redirect->toRoute(
            '@catalog_product_images',
            ['product_id' => $image->getProduct()->getId()]
        );
    }

    /**
     * @throws ORMException
     * @throws FilesystemException
     * @throws RuntimeError
     * @throws DependencyException
     * @throws LoaderError
     * @throws OptimisticLockException
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NotSupported
     * @throws NoResultException
     */
    #[Route(
        path: '/images/delete',
        name: 'images_delete',
        options: [
            'comment' => 'Удаление изображения к товару'
        ]
    )]
    public function imagesDelete(EntityManager $em): ResponseInterface
    {
        $image = $em->getRepository(Image::class)->find(
            $this->request->getQueryParams()['id'] ?? 0
        ) ?? throw new NoResultException();

        $this->breadcrumbs
            ->setLastBreadcrumb('Удаление изображения');


        $form = new Form();

        $form->header('Подтвердите удаление!');
        $form->submit('delete');

        if ($form->isSubmitted()) {
            $filesystem = $this->config->getImageStorageUpload($image->getStorage())->getFileSystem();


            $product = $image->getProduct();

            $filesystem->delete($image->getFilename() . '.' . $image->getExtension());
            $filesystem->delete($image->getFilename() . '_small.' . $image->getExtension());
            $filesystem->delete($image->getFilename() . '_large.' . $image->getExtension());

            $em->remove($image);
            $em->flush();

            if ($image->isGeneral()) {
                $nextImage = $product->getImages()->first();
                if ($nextImage instanceof Image) {
                    $nextImage->setGeneral(true);
                }
                $em->flush();
            }

            return $this->redirect->toRoute(
                '@catalog_product_images', ['product_id' => $product->getId()]
            );
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'form' => $rendererForm
                ]
            )
        );
    }

    #[Route(
        path: '/images/upload-dropzone',
        name: 'images_upload_dropzone',
        options: [
            'comment' => '[Admin][Simple Gallery] Загрузка изображений с помощью dropzone.js'
        ]
    )]
    public function imagesUploadDropzone(
        EntityManager $em,
        UploadHandler $uploadHandler
    ): ResponseInterface {
        try {
            $product = $this->product ?? throw new NoResultException();

            $file = $uploadHandler->uploadFile($this->request->getUploadedFiles()['image']);
            $manageImage = new ManageImage($product, $em, $this->config);
            $manageImage->addToDB(
                str_replace($file->getFileInfo()->getExtensionWithDot(), '', $file->getTargetPath()),
                $file->getFileInfo()->getExtension()
            );
        } catch (Throwable $e) {
            $this->response = $this->response->withStatus(500);
            $errorMessage = htmlspecialchars(sprintf('%s: %s', $e::class, $e->getMessage()));
        }
        return $this->jsonResponse($errorMessage ?? 'uploaded');
    }

    /**
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     * @throws NoResultException
     */
    #[Route(
        path: '/{product_id}/files',
        name: "files",
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Менеджер файлов (продукт)'
    )]
    public function files(): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $this->twig->addExtension($this->container->get(ConvertSize::class));

        $this->breadcrumbs
            ->add(['@catalog_product_files', ['product_id' => $product->getId()]], 'Менеджер файлов')
            ->setLastBreadcrumb(
                sprintf('Менеджер файлов: %s', $product->getName())
            );

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/files/manage.twig',
                [
                    'product' => $product,
                    'config' => $this->config,
                    'subtitle' => 'Управление файлами'
                ]
            )
        );
    }

    /**
     * @throws ExceptionRule
     * @throws ORMException
     * @throws RuntimeError
     * @throws FilesystemException
     * @throws DependencyException
     * @throws LoaderError
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws NoResultException
     */
    #[Route(
        path: "/{product_id}/files/upload",
        name: "files_upload",
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Загрузка файла (продукт)'
    )]
    public function filesUpload(FileUploadProductForm $upload, EntityManager $em): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $form = $upload->getForm();

        if ($form->isSubmitted()) {
            $file = $upload->doAction($em, $this->request, $product, $this->config);
            $this->dispatcher->dispatch(new PostUploadFile($file));
            return $this->redirect->toRoute(
                '@catalog_product_files',
                ['product_id' => $this->product->getId()]
            );
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs
            ->add(['@catalog_product_files', ['product_id' => $product->getId()]], 'Менеджер файлов')
            ->setLastBreadcrumb(sprintf('Загрузка файла для %s', $product->getName()));

        return $this->response(
            $this->twig->render(
                $this->templatePath . '/form.twig',
                [
                    'product' => $product,
                    'form' => $rendererForm,
                    'subtitle' => 'Загрузка файла',
                ]
            )
        );
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws FilesystemException
     * @throws NotSupported
     * @throws NoResultException
     */
    #[Route(
        path: "/{product_id}/files/delete",
        name: "files_delete",
        requirements: [
            'product_id' => Requirement::UUID
        ],
        comment: 'Удалить загруженный файл (продукт)'
    )]
    public function filesDelete(EntityManager $em): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        /** @var ProductFiles $file */
        $file = $em->getRepository(ProductFiles::class)->findOneBy([
            'id' => $this->request->getQueryParams()['id'] ?? 0,
            'product' => $product,
        ]) ?? throw new NoResultException();

        $em->remove($file);
        $em->flush();

        $filesystem = $this->config->getFileStorageUpload($file->getStorage())->getFileSystem();
        $filesystem->delete($file->getFilePath());

        return $this->redirect->toRoute('@catalog_product_files', [
            'product_id' => $product->getId()
        ]);
    }

    /**
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
        path: '/prices',
        name: 'prices',
        comment: 'Установка цен товару'
    )]
    public function prices(PriceProductForm $priceProductForm): ResponseInterface
    {
        $product = $this->product ?? throw new NoResultException();

        $form = $priceProductForm->getForm($product);

        if ($form->isSubmitted()) {
            $priceProductForm->doAction($product);

            return $this->redirect->toUrl();
        }

        $rendererForm = $this->adminConfig->getRendererForm($form);

        $this->breadcrumbs->setLastBreadcrumb(
            sprintf('Менеджер цен: %s', $this->product->getName())
        );


        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/prices/manage.twig',
                [
                    'product' => $this->product,
                    'form' => $rendererForm->output(),
                    'subtitle' => 'Установка цен'
                ]
            )
        );
    }


    /**
     * @throws SyntaxError
     * @throws NotFoundException
     * @throws \ReflectionException
     * @throws RuntimeError
     * @throws LoaderError
     * @throws DependencyException
     */
    #[Route(
        path: '/options',
        name: 'options',
        comment: 'Просмотр опций товара'
    )]
    public function options(ManageOptions $manageOptions): ResponseInterface
    {
        $form = $manageOptions->getForm();

        if ($form->isSubmitted()) {
            $manageOptions->doSave();
            return $this->redirect->toUrl();
        }

        $this->breadcrumbs->setLastBreadcrumb(
            sprintf('Характеристики: %s', $manageOptions->getProduct()->getName())
        );

        /** @var Bootstrap4Renderer $renderer */
        $renderer = $this->adminConfig->getRendererForm($form);
        return $this->response(
            $this->twig->render(
                $this->templatePath . '/product/options/options.twig',
                [
                    'product' => $manageOptions->getProduct(),
                    'form' => $form,
                    'renderer' => $renderer,
                    'delimiterOptions' => $this->config->getDelimiterOptions(),
                    'subtitle' => 'Параметры'
                ]
            )
        );
    }

}
