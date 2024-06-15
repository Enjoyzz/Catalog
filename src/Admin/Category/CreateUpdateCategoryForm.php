<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Category;


use Doctrine\Common\Collections\Criteria;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query\QueryException;
use Enjoys\Forms\Elements\Html;
use Enjoys\Forms\Elements\Text;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CreateUpdateCategoryForm
{

    public function __construct(
        private readonly EntityManager $em,
        private readonly ServerRequestInterface $request,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly \EnjoysCMS\Module\Catalog\Repository\Category $repository,
    ) {
    }


    /**
     * @throws ExceptionRule
     * @throws QueryException
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    public function getForm(?Category $category = null): Form
    {
        $form = new Form();


        $form->setDefaults(
            [
                'parent' => $category?->getParent()?->getId() ?? $this->request->getQueryParams()['parent_id'] ?? null,
                'title' => $category?->getTitle(),
                'description' => $category?->getDescription(),
                'shortDescription' => $category?->getShortDescription(),
                'url' => $category?->getUrl(),
                'img' => $category?->getImg(),
                'status' => [(int)($category?->isStatus() ?? true)],
                'extraFields' => array_map(
                    function ($item) {
                        return $item->getId();
                    },
                    $category?->getExtraFields()->toArray() ?? []
                ),
                'customTemplatePath' => $category?->getCustomTemplatePath(),
                'meta-title' => $category?->getMeta()->getTitle(),
                'meta-description' => $category?->getMeta()->getDescription(),
                'meta-keywords' => $category?->getMeta()->getKeyword(),
            ]
        );

        $form->checkbox('status')
            ->addClass(
                'custom-switch custom-switch-off-danger custom-switch-on-success',
                Form::ATTRIBUTES_FILLABLE_BASE
            )
            ->fill([1 => 'Статус категории']);


        $form->select('parent', 'Родительская категория')
            ->addRule(Rules::REQUIRED)
            ->fill(
                ['0' => '_без родительской категории_'] + $this->repository->getFormFillArray(criteria: [
                    Criteria::create()->where(Criteria::expr()->neq('id', $category?->getId() ?? ''))
                ])
            );

        $form->text('title', 'Наименование')
            ->addRule(Rules::REQUIRED);

        $form->text('url', 'URL')
            ->addRule(Rules::REQUIRED)
            ->addRule(
                Rules::CALLBACK,
                'Ошибка, такой url уже существует',
                function () use ($category) {
                    $url = $this->request->getParsedBody()['url'] ?? null;
                    $parent = $this->repository->find(
                        $this->request->getParsedBody()['parent'] ?? null
                    );
                    if ($url === $category?->getUrl() && $parent === $category?->getParent()) {
                        return true;
                    }

                    $check = $this->repository->findOneBy(
                        [
                            'url' => $url,
                            'parent' => $parent
                        ]
                    );

                    return is_null($check);
                }
            );
        $form->textarea('shortDescription', 'Короткое описание');
        $form->textarea('description', 'Описание');


        $form->group('Изображение')
            ->add(
                [
                    new Text('img'),
                    new Html(
                        <<<HTML
<a class="btn btn-default btn-outline btn-upload"  id="inputImage" title="Upload image file">
    <span class="fa fa-upload "></span>
</a>
HTML
                    ),
                ]
            );

        $linkFillFromParent = $category?->getParent() ? '<a class="align-top btn btn-xs btn-warning"
                id="fill-from-parent"
                data-id="' . $category?->getId() . '">
                Заполнить из родительской категории</a>' : '';

        $linkFillAllChildren = $category?->getChildren()->count() ? '<a class="align-top btn btn-xs btn-info"
                id="fill-all-children"
                href="' . $this->urlGenerator->generate(
                '@catalog_category_set-extra-fields-to-children',
                ['id' => $category?->getId()]
            ) . '">
                    Заполнить все дочерние категории</a>' : '';


        $form->select(
            'extraFields',
            "Дополнительные поля $linkFillFromParent $linkFillAllChildren "
        )
            ->setDescription(
                'Дополнительные поля, которые можно отображать в списке продуктов.
                Берутся из параметров товара (опций)'
            )->addClass('set-extra-fields')
            ->setMultiple()
            ->fill(function () use ($category) {
                $optionKeys = $this->em->getRepository(OptionKey::class)->findBy(
                    [
                        'id' => array_map(
                            function ($item) {
                                return $item->getId();
                            },
                            $category?->getExtraFields()->toArray() ?? []
                        )
                    ]
                );
                $result = [];
                foreach ($optionKeys as $key) {
                    $result[$key->getId()] = [
                        $key->getName() . (($key->getUnit()) ? ' (' . $key->getUnit() . ')' : ''),
                        ['id' => uniqid()]
                    ];
                }
                return $result;
            });

        $form->text('customTemplatePath', 'Пользовательский шаблон отображения категории')
            ->setDescription(
                '(Не обязательно) Путь к шаблону или другая информация, способная поменять отображение товаров в группе'
            );


        $form->text('meta-title', 'meta-title');
        $form->textarea('meta-description', 'meta-description');
        $form->text('meta-keywords', 'meta-keywords');

        $form->submit('add');
        return $form;
    }


    /**
     * @throws NotSupported
     * @throws ORMException
     */
    public function doAction(Category $category = null): Category
    {
        $category = $category ?? new Category();
        $category->setSort(0);
        $category->setParent($this->repository->find($this->request->getParsedBody()['parent'] ?? null));
        $category->setTitle($this->request->getParsedBody()['title'] ?? null);
        $category->setDescription($this->request->getParsedBody()['description'] ?? null);
        $category->setShortDescription($this->request->getParsedBody()['shortDescription'] ?? null);
        $category->setUrl($this->request->getParsedBody()['url'] ?? null);
        $category->setStatus((bool)($this->request->getParsedBody()['status'] ?? false));
        $category->setImg($this->request->getParsedBody()['img'] ?? null);
        $category->setCustomTemplatePath($this->request->getParsedBody()['customTemplatePath'] ?? null);

        $meta = $category->getMeta();
        $meta->setTitle($this->request->getParsedBody()['meta-title'] ?? null);
        $meta->setDescription($this->request->getParsedBody()['meta-description'] ?? null);
        $meta->setKeyword($this->request->getParsedBody()['meta-keywords'] ?? null);
        $this->em->persist($meta);

        $category->setMeta($meta);

        $extraFields = $this->em->getRepository(OptionKey::class)->findBy(
            ['id' => $this->request->getParsedBody()['extraFields'] ?? 0]
        );

        $category->removeExtraFields();
        foreach ($extraFields as $extraField) {
            $category->addExtraField($extraField);
        }
        $this->em->persist($category);

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            $this->repository->recover();
            $this->repository->updateLevelValues();
            $this->em->flush();
        }

        return $category;
    }

}
