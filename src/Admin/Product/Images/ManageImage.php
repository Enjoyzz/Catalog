<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Images;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\Image;
use EnjoysCMS\Module\Catalog\Entity\Product;

final class ManageImage
{

    /**
     * @var Image[]
     */
    private array $productImages;

    public function __construct(
        private readonly Product $product,
        private readonly EntityManagerInterface $entityManager,
        private readonly Config $config,
    ) {
        $this->productImages = $entityManager->getRepository(Image::class)->findBy(['product' => $this->product]);
    }

    public function addToDB(string $filename, string $extension): void
    {
        $image = new Image();
        $image->setProduct($this->product);
        $image->setFilename($filename);
        $image->setExtension($extension);
        $image->setStorage($this->config->get('productImageStorage'));
        $image->setGeneral(empty($this->productImages));

        $this->entityManager->persist($image);
        $this->entityManager->flush();
    }

}
