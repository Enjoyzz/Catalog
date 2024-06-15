<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Category;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\Mapping\MappingException;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\Product;
use Psr\Http\Message\ServerRequestInterface;

final class Delete
{

    public function __construct(
        private readonly EntityManager $em,
        private readonly \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository,
        private readonly \EnjoysCMS\Module\Catalog\Repository\Product $productRepository,
        private readonly ServerRequestInterface $request,
    ) {
    }


    public function getForm(Category $category): Form
    {
        $form = new Form();
        $form->setDefaults([
            'set_parent_category' => [0]
        ]);
        $form->header('Подтвердите удаление!');
        $form->checkbox('remove_childs')->fill(['+ Удаление дочерних категорий']);
        $form->checkbox('set_parent_category')->setPrefixId('set_parent_category')->fill(
            [
                sprintf(
                    'Установить для продуктов из удаляемых категорий родительскую категорию (%s)',
                    $category->getParent()?->getTitle() ?? 'без родительской категории'
                )
            ]
        );
        $form->submit('delete', 'Удалить')->addClass('btn btn-danger');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws MappingException
     */
    public function doAction(Category $category): void
    {
        $setCategory = (($this->request->getParsedBody(
            )['set_parent_category'] ?? null) !== null) ? $category->getParent() : null;

        $this->em->remove($category->getMeta());

        if (($this->request->getParsedBody()['remove_childs'] ?? null) !== null) {
            $allCategoryIds = $this->categoryRepository->getAllIds($category);
            /** @var Product[] $products */
            $products = $this->productRepository->findByCategorysIds($allCategoryIds);
            $this->setCategory($products, $setCategory);

            $this->em->remove($category);
            $this->em->flush();
        } else {
            /** @var Product[] $products */
            $products = $this->productRepository->findByCategory($category);
            $this->setCategory($products, $setCategory);

            $this->categoryRepository->removeFromTree($category);
            $this->categoryRepository->updateLevelValues();
            $this->em->clear();
        }
    }

    /**
     * @param Product[] $products
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function setCategory(array $products, ?Category $category = null): void
    {
        foreach ($products as $product) {
            $product->setCategory($category);
        }
        $this->em->flush();
    }

}
