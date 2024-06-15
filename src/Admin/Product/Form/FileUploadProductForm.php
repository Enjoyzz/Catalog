<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Form;


use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use Enjoys\Upload\UploadProcessing;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductFiles;
use Exception;
use InvalidArgumentException;
use League\Flysystem\FilesystemException;
use Psr\Http\Message\ServerRequestInterface;

final class FileUploadProductForm
{


    /**
     * @throws ExceptionRule
     */
    public function getForm(): Form
    {
        $form = new Form();
        $form->text('title', 'Наименование')->setDescription('Не обязательно');
        $form->text('description', 'Описание')->setDescription('Не обязательно');
        $form->file('file', 'Выберите файл')->addRule(Rules::UPLOAD, ['required']);
        $form->submit(uniqid('submit'));
        return $form;
    }

    /**
     * @throws FilesystemException
     */
    public function doAction(
        EntityManagerInterface $em,
        ServerRequestInterface $request,
        Product $product,
        Config $config
    ): UploadProcessing {
        $uploadedFile = $request->getUploadedFiles()['file'] ?? throw new InvalidArgumentException(
            'File not choose or send'
        );
        $storage = $config->getFileStorageUpload();
        $filesystem = $storage->getFileSystem();

        $file = new UploadProcessing($uploadedFile, $filesystem);

        $newName = md5((string)microtime(true));
        $file->setFilename($newName[0] . '/' . $newName);
        try {
            $file->upload();

            $productFile = new ProductFiles();
            $productFile->setProduct($product);
            $productFile->setFilePath($file->getFileInfo()->getFilename());
            $productFile->setFileSize($file->getFileInfo()->getSize());
            $productFile->setFileExtension($file->getFileInfo()->getExtension());
            $productFile->setOriginalFilename($file->getFileInfo()->getOriginalFilename());
            $productFile->setDescription($request->getParsedBody()['description'] ?? null);
            $productFile->setTitle($request->getParsedBody()['title'] ?? null);
            $productFile->setStorage($config->get('productFileStorage'));

            $em->persist($productFile);
            $em->flush();
            return $file;
        } catch (Exception $e) {
            throw $e;
        }
    }
}
