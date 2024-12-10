<?php

namespace EnjoysCMS\Module\Catalog\Admin\ProductGroup;

use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\ProductGroup;

class RemoveProductGroupForm
{

    public function getForm(ProductGroup $productGroup): Form
    {
        $form = new Form();
        $form->submit('remove', 'Удалить (расформировать) группу товаров');
        return $form;
    }
}
