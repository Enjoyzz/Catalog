<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\ProductGroup;

use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\ProductGroupOption;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;

final class AdvancedGroupOptionForm
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
    }

    /**
     * @param ProductGroupOption[] $productGroupOptions
     * @return Form
     */
    public function getForm(array $productGroupOptions): Form
    {
        $form = new Form();
        $defaults = array_map(function (ProductGroupOption $item, $i) {
            return [
                'id' => $i,
                'order' => $item->getOrder(),
                'type' => $item->getType(),
                'extra' => $item->getExtra(),
            ];
        }, $productGroupOptions, array_keys($productGroupOptions));

        $form->setDefaults([
            'order' => array_column($defaults, 'order', 'id'),
            'type' => array_column($defaults, 'type', 'id'),
            'extra' => array_map(function ($data) {
                if ($data === null) {
                    return null;
                }
                return Yaml::dump($data);
            }, array_column($defaults, 'extra', 'id')),
        ]);

        foreach ($productGroupOptions as $i => $relation) {
            $form->header($relation->getOptionKey()->getName())->addClass('font-weight-bold mt-5');
            $form->number(sprintf('order[%s]', $i), 'Порядок сортировки');
            $form->select(sprintf('type[%s]', $i), 'Тип')
                ->fill(['button', 'image', 'thumbs', 'color'], true);


            $form->textarea(sprintf('extra[%s]', $i), 'Параметры (YAML синтаксис)')
                ->addClass('extra-textarea')
                ->setDescription(
                    '<strong>Значения (id => value):</strong> <br>' .
                    implode('<br>', array_map(function ($i) {
                        return sprintf('%s => %s', $i->getId(), $i->getValue());
                    }, $relation->getProductGroup()->getOptionsValues()->offsetGet($relation))) .
                    '<br> устанавливать в параметр <strong>map</strong>'
                );
        }


        $form->submit('edit');
        return $form;
    }

    /**
     * @param ProductGroupOption[] $productGroupOptions
     */
    public function doAction(array $productGroupOptions): void
    {
        foreach ($this->request->getParsedBody()['order'] ?? [] as $i => $order) {
            $productGroupOption = $productGroupOptions[$i] ?? false;
            if ($productGroupOption === false) {
                continue;
            }
            $productGroupOption->setOrder((int)$order);
        }

        foreach ($this->request->getParsedBody()['type'] ?? [] as $i => $type) {
            $productGroupOption = $productGroupOptions[$i] ?? false;
            if ($productGroupOption === false) {
                continue;
            }
            $productGroupOption->setType($type);
        }

        foreach ($this->request->getParsedBody()['extra'] ?? [] as $i => $extra) {
            $productGroupOption = $productGroupOptions[$i] ?? false;
            if ($productGroupOption === false) {
                continue;
            }
            $productGroupOption->setExtra(Yaml::parse($extra));
        }
        $this->em->flush();
    }
}
