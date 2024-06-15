<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Product\Form;

use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\Product;
use EnjoysCMS\Module\Catalog\Entity\ProductTag;
use Psr\Http\Message\ServerRequestInterface;

use function trim;

class TagsProductForm
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
    }


    /**
     * @throws ExceptionRule
     */
    public function getForm(Product $product): Form
    {
        $form = new Form();

        $form->setMethod('post');

        $form->setDefaults([
            'tags' => implode(
                ',',
                array_map(function ($tag) use ($product) {
                    return trim($tag->getName());
                }, $product->getTags()->toArray())
            )
        ]);

        $form->text('tags', 'Теги')->setDescription('Теги через запятую')->setAttributes(
            AttributeFactory::createFromArray(['placeholder' => ''])
        );

        $form->submit('submit1', 'Изменить');

        return $form;
    }

    public function doAction(Product $product): void
    {
        $tags = array_map('trim', array_unique(explode(',', $this->request->getParsedBody()['tags'] ?? null)));
        $product->clearTags();
        $product->addTagsFromArray($this->getTagsFromArray($tags));
        $this->em->flush();
    }

    private function getTagsFromArray(array $tags = []): array
    {
        $ret = [];
        foreach ($tags as $tag) {
            if ($tag instanceof ProductTag) {
                $ret[] = $tag;
                continue;
            }

            if (empty($tag)) {
                continue;
            }

            $tagEntity = $this->em->getRepository(ProductTag::class)->findOneBy(['name' => $tag]);

            if ($tagEntity === null) {
                $tagEntity = new ProductTag();
                $tagEntity->setName($tag);
                $this->em->persist($tagEntity);
            }
            $ret[] = $tagEntity;
        }
        $this->em->flush();

        return $ret;
    }

}
