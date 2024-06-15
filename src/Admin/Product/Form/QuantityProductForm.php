<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Product\Form;

use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\Product;
use Psr\Http\Message\ServerRequestInterface;

final class QuantityProductForm
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
    }


    public function getForm(Product $product): Form
    {
        $quantity = $product->getQuantity();
        $form = new Form();
        $form->setDefaults([
            'qty' => $quantity->getQty(),
            'min' => $quantity->getMin(),
            'step' => $quantity->getStep()
        ]);

        $form->header(sprintf('Единица измерения: %s', $product->getUnit()?->getName() ?? '-'));

        $form->text('qty', 'Количество');
        $form->header('Дополнительные параметры');
        $form->text('min', 'Минимальное кол-во для заказа');
        $form->text('step', 'Шаг');


        $form->submit('set', 'Установить');
        return $form;
    }

    public function doAction(Product $product): void
    {
        $quantity = $product->getQuantity();

        $quantity->setQty($this->request->getParsedBody()['qty'] ?? null);
        $quantity->setStep($this->request->getParsedBody()['step'] ?? null);
        $quantity->setMin($this->request->getParsedBody()['min'] ?? null);

        $this->em->flush();
    }
}
