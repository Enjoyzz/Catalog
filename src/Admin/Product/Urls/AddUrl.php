<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Urls;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use EnjoysCMS\Module\Catalog\Entities\Product;
use EnjoysCMS\Module\Catalog\Entities\Url;
use EnjoysCMS\Module\Catalog\Repositories\Product as ProductRepository;
use Psr\Http\Message\ServerRequestInterface;

final class AddUrl
{
    private EntityRepository|ProductRepository $productRepository;


    /**
     * @throws NotSupported
     * @throws NoResultException
     */
    public function __construct(
        private readonly EntityManager $em,
        private readonly ServerRequestInterface $request,
    ) {
        $this->productRepository = $this->em->getRepository(Product::class);
    }




    /**
     * @throws ExceptionRule
     */
    public function getForm(Product $product): Form
    {
        $form = new Form();

        $form->checkbox('default')
            ->addClass(
                'custom-switch custom-switch-off-danger custom-switch-on-success',
                Form::ATTRIBUTES_FILLABLE_BASE
            )
            ->fill([1 => 'Сделать основным?']);

        $form->text('path', 'Путь')->addRule(Rules::REQUIRED)
            ->addRule(
                Rules::CALLBACK,
                'Ошибка, такой url уже существует',
                function () use ($product) {
                    /** @var Product $product */
                    $product = $this->productRepository->getFindByUrlBuilder(
                        $this->request->getParsedBody()['path'] ?? null,
                        $product->getCategory()
                    )->getQuery()->getOneOrNullResult();

                    return is_null($product);
                }
            );
        $form->submit('save', 'Добавить');
        return $form;
    }


    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function doAction(Product $product): void
    {
        $url = new Url();
        $url->setPath($this->request->getParsedBody()['path'] ?? null);
        $url->setDefault((bool)($this->request->getParsedBody()['default'] ?? false));
        $url->setProduct($product);

        if ($url->isDefault()) {
            foreach ($product->getUrls() as $item) {
                $item->setDefault(false);
            }
        }

        $this->em->persist($url);
        $this->em->flush();

    }
}
