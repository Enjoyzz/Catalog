<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Product\Options;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NoResultException;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Elements\Html;
use Enjoys\Forms\Elements\Radio;
use Enjoys\Forms\Elements\Select;
use Enjoys\Forms\Elements\Text;
use Enjoys\Forms\Elements\Textarea;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\ElementInterface;
use Enjoys\Forms\Renderer\Bootstrap4\Group;
use EnjoysCMS\Module\Catalog\Api\ProductOptions;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\OptionValue;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Repository\OptionKeyRepository;
use EnjoysCMS\Module\Catalog\Repository\OptionValueRepository;
use EnjoysCMS\Module\Catalog\Repository\Product as ProductRepository;
use JMS\Serializer\SerializerBuilder;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ManageOptions
{
    private EntityRepository|ProductRepository $productRepository;
    private Product $product;
    private EntityRepository|OptionKeyRepository $keyRepository;
    private EntityRepository|OptionValueRepository $valueRepository;

    /**
     * @throws NoResultException
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
        private readonly ProductOptions $productOptionsController,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        $this->keyRepository = $this->em->getRepository(OptionKey::class);
        $this->valueRepository = $this->em->getRepository(OptionValue::class);
        $this->productRepository = $this->em->getRepository(Product::class);
        $this->product = $this->productRepository->find(
            $this->request->getQueryParams()['product_id'] ?? null
        ) ?? throw new NoResultException();
    }


    /**
     * @throws \ReflectionException
     */
    public function getForm(): Form
    {
        $serializer = SerializerBuilder::create()->build();
        /** @var OptionKey[] $optionKeys */
        $optionKeys = $serializer->deserialize(
            (string)$this->productOptionsController->getProductOptionsKeysByCategory(
                $this->product->getCategory()
            )->getBody(),
            'array<EnjoysCMS\Module\Catalog\Entity\OptionKey>',
            'json'
        );

        $form = new Form();
        $form->setDefaults($this->getDefaultsOptions($this->product->getOptions()));

        foreach ($optionKeys as $optionKey) {
            $form->group()
                ->add([
                    (new Text(
                        'options[' . $optionKey->getId() . '][option]'
                    ))
                        ->setDescription($optionKey->getNote())
                        ->setAttributes(
                            AttributeFactory::createFromArray([
                                'class' => 'option-key form-control',
                                'placeholder' => 'Опция',
                                'grid' => 'col-md-3',
                                'value' => $optionKey->getName()
                            ])
                        )->addClass('col-md-3', Group::ATTRIBUTES_GROUP),
                    (new Text(
                        'options[' . $optionKey->getId() . '][unit]'
                    ))->setAttributes(
                        AttributeFactory::createFromArray([
                            'class' => 'option-unit form-control',
                            'placeholder' => 'ед.изм.',
                            'grid' => 'col-md-1',
                            'autocomplete' => 'off',
                            'value' => $optionKey->getUnit()
                        ])
                    )->addClass('col-md-1', Group::ATTRIBUTES_GROUP),
                    $this->getValueInputElement($optionKey),
                    (new Html(
                        sprintf(
                            '<a href="%s" class="btn btn-link"><i class="fa fa-edit"></i></a> <a role="button" class="remove-option btn btn-link"><i class="fa fa-trash"></i></a>',
                            $this->urlGenerator->generate('@catalog_product_options_edit', [
                                'key_id' => $optionKey->getId()
                            ])
                        )
                    ))->setAttributes(
                        AttributeFactory::createFromArray([
                            'class' => 'form-control',
                            'grid' => 'col-md-1'
                        ]),
                    )
                        ->addClass('col-md-1', Group::ATTRIBUTES_GROUP)
                ]);
        }
        $form->submit('submit', 'Сохранить')->addClasses(['btn', 'btn-primary']);
        $form->button('add', 'Добавить характеристику')->addClasses(['add-option'])
            ->addAttributes(
                AttributeFactory::createFromArray([
                    'type' => 'button'
                ])
            );
        $form->button('fill_by', 'Заполнить по &hellip;')
            ->addAttributes(
                AttributeFactory::createFromArray([
                    'type' => 'button',
                    'data-toggle' => "modal",
                    'data-target' => "#fill-by-modal"
                ])
            )
            ->removeAttribute('name')
            ->removeAttribute('id');

        return $form;
    }

    private function getDefaultsOptions($options): array
    {
        $defaults = [];

        foreach ($options as $option) {
            $defaults['options'][$option['key']->getId()]['value'] = array_map(function (OptionValue $item) {
                return $item->getRawValue();
            }, $option['values']);
        }
        return $defaults;
    }


    public function doSave(): void
    {
        $this->product->clearOptions();
        foreach (($this->request->getParsedBody()['options'] ?? []) as $option) {
            if (empty($option['option']) || empty($option['value'])) {
                continue;
            }
            $optionKey = $this->keyRepository->getOptionKey($option['option'], $option['unit']);
            foreach ($option['value'] as $value) {
                if ($value === '' || is_null($value)) {
                    continue;
                }
                $optionValue = $this->valueRepository->getOptionValue($value, $optionKey);
                $this->em->persist($optionValue);
                $this->product->addOption($optionValue);
            }
        }
        $this->em->flush();
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * @throws \ReflectionException
     */
    private function getValueInputElement(OptionKey $optionKey): ElementInterface
    {
        return match ($optionKey->getType()) {
            OptionType::ENUM, OptionType::NUMERIC => $this->getSelectValue($optionKey),
            OptionType::BOOL => $this->getSwitchValue($optionKey),
            OptionType::TEXT => $this->getTextValue($optionKey),
        };
    }

    private function getSelectValue(OptionKey $optionKey): Select
    {
        $element = new Select(
            'options[' . $optionKey->getId() . '][value][]'
        );

        if ($optionKey->isMultiple()) {
            $element->setMultiple();
        }

        $element->setAttributes(
            AttributeFactory::createFromArray([
                'data-tags' => 'true'
            ])
        );
        $element->addAttribute(
            AttributeFactory::create('data-placeholder', 'Введите значение, или оставьте пустым')
        )
            ->addClasses(['option-value', '__value-type-select', 'form-control'])
            ->addClass('col-md-7', Group::ATTRIBUTES_GROUP)
            ->fill(function () use ($optionKey) {
                return array_map(function (OptionValue $value) {
                    return $value->getValue();
                }, $this->product->getValuesByOptionKey($optionKey));
            }, true);

        return $element;
    }

    private function getSwitchValue(OptionKey $optionKey): Radio
    {
        $element = new Radio('options[' . $optionKey->getId() . '][value][]');

        $element->fill([1 => $optionKey->getParams()[1] ?? 'Да', 0 => $optionKey->getParams()[0] ?? 'Нет']);
        return $element;
    }

    private function getTextValue(OptionKey $optionKey): Textarea
    {
        return new Textarea('options[' . $optionKey->getId() . '][value][]');
    }

}
