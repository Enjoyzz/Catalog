<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Form;


use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\Url;
use EnjoysCMS\Module\Catalog\Repository\Product as ProductRepository;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUpdateUrlProductForm
{

    private EntityRepository|ProductRepository $productRepository;


    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
        $this->productRepository = $this->em->getRepository(Product::class);
    }


    /**
     * @throws ExceptionRule
     */
    public function getForm(Product $product): Form
    {
        $url = $product->getUrlById((int)($this->request->getQueryParams()['url_id'] ?? 0));

        $form = new Form();

        $form->setDefaults([
            'path' => $url?->getPath(),
            'default' => [$url?->isDefault() ? 1 : 0]
        ]);

        $form->checkbox('default')
            ->addClass(
                'custom-switch custom-switch-off-danger custom-switch-on-success',
                Form::ATTRIBUTES_FILLABLE_BASE
            )
            ->fill([1 => 'Сделать основным?']);

        $form->text('path', 'Путь')->addRule(Rules::REQUIRED)
            ->addRule(Rules::CALLBACK, 'Не допустимые символы', function () {
                preg_match('/[.\/]/', $this->request->getParsedBody()['path'] ?? '', $matches);
                return !$matches;
            })
            ->addRule(
                Rules::CALLBACK,
                'Ошибка, такой url уже существует',
                function () use ($product, $url) {
                    if ($url?->getPath() === ($this->request->getParsedBody()['path'] ?? null)) {
                        return true;
                    }

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
        $url = $product->getUrlById((int)($this->request->getQueryParams()['url_id'] ?? 0));


        $url = $url ?? new Url();
        $url->setPath($this->request->getParsedBody()['path'] ?? null);

        $default = (bool)($this->request->getParsedBody()['default'] ?? false);
        if ($default) {
            foreach ($product->getUrls() as $item) {
                $item->setDefault(false);
            }
        }
        $url->setDefault($default);
        $url->setProduct($product);

        $this->em->persist($url);
        $this->em->flush();
    }

}
