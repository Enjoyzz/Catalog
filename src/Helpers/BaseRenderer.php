<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Helpers;

use Enjoys\Forms\Renderer\Html\HtmlRenderer;

class BaseRenderer extends HtmlRenderer
{
    public function rendererElements(): string
    {
        $html = [];
        foreach ($this->getForm()->getElements() as $element) {
            $html[] = self::createTypeRender($element)->render();
        }
        return implode("\n", $html);
    }

    public function rendererElement(string $elementName): string
    {
        $element = $this->getForm()->getElement($elementName);
        if ($element === null) {
            return '';
        }
        return self::createTypeRender($element)->render();
    }

}
