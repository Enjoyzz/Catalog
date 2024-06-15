<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Options;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NoResultException;
use EnjoysCMS\Core\Http\Response\RedirectInterface;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Repository\Product as ProductRepository;
use Psr\Http\Message\ServerRequestInterface;

final class FillFromProduct
{
    private EntityRepository|ProductRepository $productRepository;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
        private readonly RedirectInterface $redirect,
    ) {
        $this->productRepository = $this->em->getRepository(Product::class);
    }

    /**
     * @throws NoResultException
     * @throws ORMException
     */
    public function __invoke(): void
    {
        /** @var Product $product */
        $product = $this->productRepository->find(
            $this->request->getParsedBody()['id'] ?? 0
        ) ?? throw new NoResultException();


        /** @var Product $from */
        $from = $this->productRepository->find($this->request->getParsedBody()['fillFromProduct'] ?? 0);
        if ($from === null) {
            $this->redirectToProductOptionsPage($product);
        }

        foreach ($from->getOptionsCollection() as $item) {
            $product->addOption($item);
        }

        $this->em->persist($product);
        $this->em->flush();

        $this->redirectToProductOptionsPage($product);
    }

    private function redirectToProductOptionsPage(Product $product): void
    {
        $this->redirect->toRoute(
            '@catalog_product_options',
            ['product_id' => $product->getId()],
            emit: true
        );
    }
}
