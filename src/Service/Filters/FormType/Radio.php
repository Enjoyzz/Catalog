<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Service\Filters\FormType;

use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterInterface;

final class Radio
{
    private bool $multiple = false;

    public function __construct(
        private Form $form,
        private FilterInterface $filter,
        private $values,
    ) {
    }

    public function create(): void
    {
         $this->form->radio(
            sprintf('%s[]', $this->filter->getFormName()),
            $this->filter->__toString()
        )->fill($this->values);
    }
}
