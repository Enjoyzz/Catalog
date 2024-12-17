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
        $form = new Form('get', $this->urlGenerator->generate('catalog/search'));
        $form->setDefaults([
            'q' => $this->request->getQueryParams()['q'] ?? null,
            'category' => $this->request->getQueryParams()['category'] ?? [],
        ]);
        $form->search('q')->addRule(Rules::LENGTH, ['>=' => 3]);
        $form->select('category')->fill(function () {
            $data = ['Все категории'];
            /** @var \EnjoysCMS\Module\Catalog\Entity\Category $category */
            foreach ($this->categoryRepository->getChildNodes() as $category) {
                $data[$category->getId()] = $category->getTitle();
            }
            return $data;
        });
        $form->submit('_submit');

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
