<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Crud\Images;

use Doctrine\ORM\EntityManager;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\RendererInterface;
use Enjoys\ServerRequestWrapper;
use EnjoysCMS\Core\Components\Helpers\Redirect;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Module\Admin\Core\ModelInterface;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entities\Image;
use EnjoysCMS\Module\Catalog\StorageUpload\StorageUploadInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class Delete implements ModelInterface
{

    private ?Image $image;
    private StorageUploadInterface $storageUpload;

    /**
     * @throws NotFoundException
     */
    public function __construct(
        private EntityManager $entityManager,
        private ServerRequestWrapper $requestWrapper,
        private RendererInterface $renderer,
        private UrlGeneratorInterface $urlGenerator,
        ContainerInterface $container
    ) {
        $config = Config::getConfig($container)->get('manageUploads')['image'] ?? throw new \RuntimeException(
                'Not set config `manageUploads.image`'
            );
        $fileSystemClass = key($config);
        $this->storageUpload = new $fileSystemClass(...current($config));



        $this->image = $this->entityManager->getRepository(Image::class)->find(
            $this->requestWrapper->getQueryData('id', 0)
        );



        if ($this->image === null) {
            throw new NotFoundException(
                sprintf('Not found by id: %s', $this->requestWrapper->getQueryData('id'))
            );
        }
    }

    public function getContext(): array
    {
        $form = $this->getForm();

        $this->renderer->setForm($form);

        if ($form->isSubmitted()) {
            $this->doAction();
        }


        return [
            'form' => $this->renderer,
            'breadcrumbs' => [
                $this->urlGenerator->generate('admin/index') => 'Главная',
                $this->urlGenerator->generate('@a/catalog/dashboard') => 'Каталог',
                $this->urlGenerator->generate('catalog/admin/products') => 'Список продуктов',
                'Удаление изображения',
            ],
        ];
    }

    private function getForm(): Form
    {
        $form = new Form();

        $form->header('Подтвердите удаление!');
        $form->submit('delete');
        return $form;
    }

    private function doAction(): void
    {
        $filesystem = $this->storageUpload->getFileSystem();


        $product = $this->image->getProduct();

        $filesystem->delete( $this->image->getFilename() . '.' . $this->image->getExtension());
        $filesystem->delete( $this->image->getFilename() . '_small.' . $this->image->getExtension());
        $filesystem->delete( $this->image->getFilename() . '_large.' . $this->image->getExtension());

//        foreach (glob($this->image->getGlobPattern()) as $item) {
//            @unlink($item);
//        }
        $this->entityManager->remove($this->image);
        $this->entityManager->flush();

        if($this->image->isGeneral()){
            $nextImage = $product->getImages()->first();
            if($nextImage instanceof Image){
                $nextImage->setGeneral(true);
            }
            $this->entityManager->flush();
        }

        Redirect::http($this->urlGenerator->generate('catalog/admin/product/images', ['product_id' => $product->getId()]));
    }
}
