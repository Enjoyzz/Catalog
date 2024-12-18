<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Controller;


use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\Exception\NotSupported;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Renderer\Html\HtmlRenderer;
use Enjoys\Forms\Renderer\Renderer;
use Enjoys\Forms\Rules;
use EnjoysCMS\Core\Pagination\Pagination;
use EnjoysCMS\Module\Catalog\Entity\Category;
use EnjoysCMS\Module\Catalog\Entity\Image;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\OptionValue;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Service\Search\DefaultSearch;
use EnjoysCMS\Module\Catalog\Service\Search\SearchInterface;
use EnjoysCMS\Module\Catalog\Service\Search\SearchQuery;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Throwable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function trim;

final class Search extends PublicController
{
    private array $optionKeys;

    private SearchInterface $search;

    private SearchQuery $searchQuery;


    /**
     * @throws NotSupported
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function __construct(
        Container $container,
        private readonly \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository
    ) {
        parent::__construct($container);
        $this->optionKeys = explode(',', $this->config->getSearchOptionField());
        $this->search = $container->get($this->config->get('searchClass', DefaultSearch::class));
    }

    private function normalizeQuery(): string
    {
        $query = trim($this->request->getQueryParams()['q'] ?? $this->request->getParsedBody()['q'] ?? '');

        if (mb_strlen($query) < $this->config->get('minSearchChars', 3)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Слишком короткое слово для поиска (нужно минимум %s символа)',
                    $this->config->get('minSearchChars', 3)
                )
            );
        }

        return $query;
    }


    #[Route(
        path: '/catalog/api/search/',
        name: 'catalog/api/search'
    )]
    public function apiSearch(): ResponseInterface
    {
        $serializer = new Serializer(
            normalizers: [new ObjectNormalizer()],
            encoders: [new JsonEncoder()]
        );

        $pagination = new Pagination(
            $this->request->getQueryParams()['page'] ?? 1, $this->config->get('limitItems', 30)
        );

        try {
            $this->searchQuery = new SearchQuery(
                query: $this->normalizeQuery(),
                optionKeys: $this->optionKeys,
                category: $this->categoryRepository->find(
                    $this->request->getQueryParams()['category'] ?? $this->request->getParsedBody()['category'] ?? 0
                ),
            );

            $searchResult = $this->search->getResult($pagination->getOffset(), $pagination->getLimitItems());

            $response = $this->json(
                $serializer->normalize(
                    $searchResult,
                    JsonEncoder::FORMAT,
                    [
                        AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                            return match ($object::class) {
                                Category::class => $object->getTitle(),
                                Product::class => $object->getName(),
                            };
                        },
                        AbstractNormalizer::CIRCULAR_REFERENCE_LIMIT => 1,
                        AbstractObjectNormalizer::ENABLE_MAX_DEPTH => true,
                        AbstractNormalizer::ATTRIBUTES => [
                            'searchQuery',
                            'countResult',
                            //'optionKeys',
                            'result' => [
                                'id',
                                'name',
                                'slug',
                                //'prices',
                                'defaultImage',
                                'options'
                            ]
                        ],
                        AbstractNormalizer::CALLBACKS => [
                            'defaultImage' => function (?Image $image) {
                                if ($image === null) {
                                    return null;
                                }
                                $storage = $this->config->getImageStorageUpload($image->getStorage());
                                return [
                                    'original' => $storage->getUrl(
                                        $image->getFilename() . '.' . $image->getExtension()
                                    ),
                                    'small' => $storage->getUrl(
                                        $image->getFilename() . '_small.' . $image->getExtension()
                                    ),
                                    'large' => $storage->getUrl(
                                        $image->getFilename() . '_large.' . $image->getExtension()
                                    ),
                                ];
                            },
                            'options' => function (array $options) {
                                $result = [];
                                /** @var list<array{key: OptionKey, values?: non-empty-list<OptionValue>}> $options */
                                foreach ($options as $option) {
                                    if (!in_array($option['key']->getId(), $this->optionKeys)) {
                                        continue;
                                    }
                                    $result[] = [
                                        'key' => $option['key']->getName(),
                                        'unit' => $option['key']->getUnit(),
                                        'values' => array_map(function ($item) {
                                            return $item->getValue();
                                        }, $option['values'] ?? []),
                                        'optionName' => $option['key']->__toString(),
                                    ];
                                }
                                return $result;
                            }
                        ]
                    ]
                )
            );
        } catch (Exception|Throwable $e) {
            $response = $this->json(['error' => $e->getMessage()]);
        } finally {
            return $response;
        }
    }

    /**
     * @return ResponseInterface
     * @throws DependencyException
     * @throws LoaderError
     * @throws NotFoundException
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws ExceptionRule
     */
    #[Route(
        path: '/catalog/search',
        name: 'catalog/search',
        priority: 2
    )]
    public function search(): ResponseInterface
    {
        $pagination = new Pagination(
            $this->request->getQueryParams()['page'] ?? 1, $this->config->get('limitItems', 30)
        );

        try {
            $this->searchQuery = new SearchQuery(
                query: $this->normalizeQuery(),
                optionKeys: $this->optionKeys,
                category: $this->categoryRepository->find($this->request->getQueryParams()['category'] ?? 0),
            );

            $result = $this->search->getResult(
                $this->searchQuery,
                $pagination->getOffset(),
                $pagination->getLimitItems()
            );
            $pagination->setTotalItems($result->getProducts()->count());
        } catch (Throwable $e) {
            $this->search->setError($e->getMessage());
        }

        $template_path = '@m/catalog/search.twig';

        if (!$this->twig->getLoader()->exists($template_path)) {
            $template_path = __DIR__ . '/../../template/search.twig';
        }

        $this->breadcrumbs->add('catalog/index', 'Каталог');
        $this->breadcrumbs->add('catalog/search', 'Поиск');

        $form = self::getSearchForm($this->request, $this->categoryRepository, $this->container->get(UrlGeneratorInterface::class));
        /** @var Renderer $renderer */
        $renderer = $this->container->get($this->config->get('search_renderer', HtmlRenderer::class));
        $renderer->setForm($form);

        return $this->response(
            $this->twig->render($template_path, [
                'pagination' => $pagination,
                'error' => $this->search->getError(),
                'searchClass' => get_debug_type($this->search),
                'searchQuery' => $this->searchQuery ?? null,
                'result' => $result ?? null,
                'breadcrumbs' => $this->breadcrumbs,
                'rendererForm' => $renderer,
            ])
        );
    }

    /**
     * @throws ExceptionRule
     */
    public static function getSearchForm(
        ServerRequestInterface $request,
        \EnjoysCMS\Module\Catalog\Repository\Category $categoryRepository,
        UrlGeneratorInterface $urlGenerator
    ): Form {
        $form = new Form('get', $urlGenerator->generate('catalog/search'));
        $form->setDefaults([
            'q' => $request->getQueryParams()['q'] ?? null,
            'category' => $request->getQueryParams()['category'] ?? [],
        ]);
        $form->search('q')->addRule(Rules::LENGTH, ['>=' => 3]);
        $form->select('category')->fill(static function () use ($categoryRepository) {
            $data = ['Все категории'];
            /** @var Category $category */
            foreach ($categoryRepository->getChildNodes() as $category) {
                $data[$category->getId()] = $category->getTitle();
            }
            return $data;
        });
        $form->submit('_submit');

        return $form;
    }
}
