<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Crud\Product;

use DI\Container;
use DI\DependencyException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectRepository;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\RendererInterface;
use Enjoys\Forms\Rules;
use EnjoysCMS\Core\Components\Helpers\Redirect;
use EnjoysCMS\Core\Components\WYSIWYG\WYSIWYG;
use EnjoysCMS\Core\Exception\NotFoundException;
use EnjoysCMS\Module\Admin\Core\ModelInterface;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entities\Category;
use EnjoysCMS\Module\Catalog\Entities\Product;
use EnjoysCMS\Module\Catalog\Entities\ProductUnit;
use EnjoysCMS\Module\Catalog\Entities\Url;
use EnjoysCMS\Module\Catalog\Helpers\URLify;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

final class Edit implements ModelInterface
{

    private ?Product $product;
    private ObjectRepository|EntityRepository|\EnjoysCMS\Module\Catalog\Repositories\Product $productRepository;


    /**
     * @throws NotFoundException
     */
    public function __construct(
        private EntityManager $entityManager,
        private ServerRequestInterface $request,
        private RendererInterface $renderer,
        private UrlGeneratorInterface $urlGenerator,
        private Container $container,
        private Config $config
    ) {
        $this->productRepository = $entityManager->getRepository(Product::class);
        $this->product = $this->productRepository->find(
            $this->request->getQueryParams()['id'] ?? 0
        );
        if ($this->product === null) {
            throw new NotFoundException(
                sprintf('Not found by id: %s', $this->request->getQueryParams()['id'] ?? null)
            );
        }
    }

    /**
     * @return array
     * @throws ExceptionRule
     * @throws LoaderError
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws DependencyException
     * @throws \DI\NotFoundException
     */
    public function getContext(): array
    {
        $form = $this->getForm();

        $this->renderer->setForm($form);
        $this->renderer->setOptions([
            'custom-switch' => true
        ]);

        if ($form->isSubmitted()) {
            $this->doAction();
        }

        $wysiwyg = WYSIWYG::getInstance($this->config->getModuleConfig()->get('WYSIWYG'), $this->container);


        return [
            'form' => $this->renderer,
            'product' => $this->product,
            'subtitle' => 'Редактирование',
            'wysiwyg' => $wysiwyg->selector('#description'),
            'breadcrumbs' => [
                $this->urlGenerator->generate('admin/index') => 'Главная',
                $this->urlGenerator->generate('@a/catalog/dashboard') => 'Каталог',
                $this->urlGenerator->generate('catalog/admin/products') => 'Список продуктов',
                sprintf('Редактирование общей информации `%s`', $this->product->getName()),
            ],
        ];
    }

    /**
     * @throws ExceptionRule
     * @throws \Exception
     */
    private function getForm(): Form
    {
        $defaults = [
            'name' => $this->product->getName(),
            'url' => $this->product->getUrl()->getPath(),
            'description' => $this->product->getDescription(),
            'unit' => $this->product->getUnit()?->getName(),
            'active' => [(int)$this->product->isActive()],
            'hide' => [(int)$this->product->isHide()],
        ];

        $category = $this->product->getCategory();
        if ($category instanceof Category) {
            $defaults['category'] = $category->getId();
        }

        $form = new Form();

        $form->setDefaults($defaults);

        $form->checkbox('active', null)
            ->setPrefixId('active')
            ->addClass(
                'custom-switch custom-switch-off-danger custom-switch-on-success',
                Form::ATTRIBUTES_FILLABLE_BASE
            )
            ->fill([1 => 'Включен?']);

        $form->checkbox('hide', null)
            ->setPrefixId('hide')
            ->addClass(
                'custom-switch custom-switch-off-danger custom-switch-on-success',
                Form::ATTRIBUTES_FILLABLE_BASE
            )
            ->fill([1 => 'Скрыт?']);

        $form->select('category', 'Категория')
            ->addRule(Rules::REQUIRED)
            ->fill(
                $this->entityManager->getRepository(
                    Category::class
                )->getFormFillArray()
            );

        $form->text('name', 'Наименование')
            ->addRule(Rules::REQUIRED);


        $form->text('url', 'URL')
            ->addRule(Rules::REQUIRED)
            ->addRule(
                Rules::CALLBACK,
                'Ошибка, такой url уже существует',
                function () {
                    /** @var Product $product */
                    $product = $this->productRepository->getFindByUrlBuilder(
                        $this->request->getParsedBody()['url'] ?? null,
                        $this->product->getCategory()
                    )->getQuery()->getOneOrNullResult();

                    if ($product === null) {
                        return true;
                    }

                    /** @var Url $url */
                    foreach ($product->getUrls() as $url) {
                        if ($url->getProduct()->getId() === $this->product->getId()) {
                            return true;
                        }
                    }

                    return false;
                }
            );
        $form->textarea('description', 'Описание');

        $form->text('unit', 'Единица измерения');


        $form->submit('add');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    private function doAction(): void
    {
        /** @var Category|null $category */
        $category = $this->entityManager->getRepository(Category::class)->find(
            $this->request->getParsedBody()['category'] ?? 0
        );

        $this->product->setName($this->request->getParsedBody()['name'] ?? null);
        $this->product->setDescription($this->request->getParsedBody()['description'] ?? null);


        $unitValue = $this->request->getParsedBody()['unit'] ?? null;
        $unit = $this->entityManager->getRepository(ProductUnit::class)->findOneBy(['name' => $unitValue]);
        if ($unit === null) {
            $unit = new ProductUnit();
            $unit->setName($unitValue);
            $this->entityManager->persist($unit);
            $this->entityManager->flush();
        }
        $this->product->setUnit($unit);

        $this->product->setCategory($category);
        $this->product->setActive((bool)($this->request->getParsedBody()['active'] ?? false));
        $this->product->setHide((bool)($this->request->getParsedBody()['hide'] ?? false));


        $urlString = (empty($this->request->getParsedBody()['url'] ?? null))
            ? URLify::slug($this->product->getName())
            : $this->request->getParsedBody()['url'] ?? null;

        /** @var Url $url */
        $urlSetFlag = false;
        foreach ($this->product->getUrls() as $url) {
            if ($url->getPath() === $urlString) {
                $url->setDefault(true);
                $urlSetFlag = true;
                continue;
            }
            $url->setDefault(false);
        }

        if ($urlSetFlag === false) {
            $url = new Url();
            $url->setPath($urlString);
            $url->setDefault(true);
            $url->setProduct($this->product);
            $this->entityManager->persist($url);
        }

        $this->entityManager->flush();
        Redirect::http($this->urlGenerator->generate('catalog/admin/products'));
    }
}
