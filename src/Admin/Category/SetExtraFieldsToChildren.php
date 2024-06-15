<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Category;


use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\Category;
use Psr\Http\Message\ServerRequestInterface;

final class SetExtraFieldsToChildren
{

    private Category $category;


    /**
     * @throws NoResultException
     */
    public function __construct(
        private readonly EntityManager $em,
        private readonly ServerRequestInterface $request,
        private readonly \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository
    ) {
        $this->category = $this->categoryRepository->find(
            $this->request->getQueryParams()['id'] ?? 0
        ) ?? throw new NoResultException();
    }

    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     * @throws ExceptionRule
     */
    public function getForm(): Form
    {
        $form = new Form();
        $form->setMethod('post');
        $form->checkbox('removeOldExtraFields')->fill(
            [1 => 'Удалить у дочерних категории все установленные extra fields и записать новые']
        );
        $form->submit('setExtraFields', 'Установить');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function doActionRecursive(Collection $collection): void
    {
        $extraFields = $this->category->getExtraFields();

        /** @var Category $item */
        foreach ($collection as $item) {
            if ($item->getChildren()->count()) {
                $this->doActionRecursive($item->getChildren());
            }


            if ($this->request->getParsedBody()['removeOldExtraFields'] ?? false) {
                $item->removeExtraFields();
            }
            foreach ($extraFields->toArray() as $optionKey) {
                $item->addExtraField($optionKey);
            }

            $this->em->persist($item);
            $this->em->flush();
        }
    }
}
