<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Product\Options;

use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use Psr\Http\Message\ServerRequestInterface;

final class EditOptions
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
    }

    /**
     * @throws ExceptionRule
     * @throws \ReflectionException
     */
    public function getForm(OptionKey $optionKey): Form
    {
        $form = new Form();
        $form->setDefaults([
            'name' => $optionKey->getName(),
            'unit' => $optionKey->getUnit(),
            'note' => $optionKey->getNote(),
            'weight' => $optionKey->getWeight(),
            'type' => $optionKey->getType()->name,
            'params' => $optionKey->getParams() === null ? null : json_encode($optionKey->getParams()),
            'multiple' => [$optionKey->isMultiple()],
            'comparable' => [$optionKey->isComparable()],
        ]);
        $form->text('name', 'Наименование')
            ->setDescription('Название характеристики.')
            ->addRule(Rules::REQUIRED);

        $form->text('unit', 'Ед. измерения')
            ->setDescription('');

        $form->textarea('note', 'Примечание')
            ->setDescription('Описание характеристики.');

        $form->select('type', 'Тип')
            ->setDescription('Тип данных.')
            ->fill(OptionType::toArray(), true);

        $form->radio('multiple', 'Мультизначения')
            ->setDescription('Можно ли передать сразу несколько значений.')
            ->fill([1 => 'Да', 0 => 'Нет']);

        $form->radio('comparable', 'Сравниваемый')
            ->setDescription('Показывать параметр при сравнении товаров')
            ->fill([1 => 'Да', 0 => 'Нет']);

        $form->textarea('params', 'Параметры')
            ->setDescription('Параметры в виде JSON строки, используются разные, при разных OptionType');

        $form->submit();
        return $form;
    }

    /**
     * @throws \ReflectionException
     */
    public function doSave(OptionKey $optionKey): OptionKey
    {
        $optionKey->setName($this->request->getParsedBody()['name'] ?? '');

        $unit = $this->request->getParsedBody()['unit'] ?? null;
        $optionKey->setUnit($unit ?: null);

        $note = $this->request->getParsedBody()['note'] ?? null;
        $optionKey->setNote($note);

        $optionKey->setType($this->request->getParsedBody()['type']);

        $optionKey->setMultiple((bool)($this->request->getParsedBody()['multiple'] ?? false));

        $optionKey->setComparable((bool)($this->request->getParsedBody()['comparable'] ?? false));

        $params = (($this->request->getParsedBody()['params'] ?? '') === '') ? null : json_decode(
            $this->request->getParsedBody()['params'] ?? '',
            true
        );
        $optionKey->setParams($params);

        $this->em->flush();
        return $optionKey;
    }
}
