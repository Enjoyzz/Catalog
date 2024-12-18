<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Blocks;


use Enjoys\Forms\Attribute;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Elements\Search;
use Enjoys\Forms\Elements\Submit;
use Enjoys\Forms\Elements\Text;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\RendererInterface;
use Enjoys\Forms\Renderer\Html\HtmlRenderer;
use Enjoys\Forms\Rules;
use Enjoys\Forms\Traits\Attributes;
use EnjoysCMS\Core\Block\AbstractBlock;
use EnjoysCMS\Core\Block\Annotation\Block;
use EnjoysCMS\Module\Catalog\Repository\Category;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

#[Block('Каталог. Поиск (форма)', [
    'template' => [
        'value' => '@m/catalog/blocks/search.twig',
        'name' => 'Путь до template',
        'description' => 'Обязательно',
    ],
])]
final class SearchBlock extends AbstractBlock
{

    public function __construct(
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ServerRequestInterface $request,
        private readonly Category $categoryRepository,
    ) {
    }


    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ExceptionRule
     */
    public function view(): string
    {
        $form = \EnjoysCMS\Module\Catalog\Controller\Search::getSearchForm($this->request, $this->categoryRepository, $this->urlGenerator);

        /** @var RendererInterface $renderer */
        $renderer = $this->getOption('renderer', HtmlRenderer::class);
        return $this->twig->render(
            $this->getOption('template'),
            [
                'form' => $form,
                'renderer' => new $renderer($form),
                'blockOptions' => $this->getOptions()
            ]
        );
    }

}
