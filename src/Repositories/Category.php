<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Repositories;


use Gedmo\Tree\Entity\Repository\ClosureTreeRepository;

class Category extends ClosureTreeRepository
{

    public function findBySlug(array $slugs)
    {
        $slug = array_shift($slugs);
        $category = $this->findOneBy(['url' => $slug]);

        if ($category === null) {
            return null;
        }

        $parent = $category->getParent();
        if ($parent !== null && !$parent->checkSlugs($slugs)) {
            return null;
        }

        return $category;
    }

    public function getTree($node = null)
    {
        return $this->childrenHierarchy(
            $node,
            false,
            [
                'childSort' => [
                    'field' => 'sort',
                    'dir' => 'asc'
                ]
            ]
        );
    }

    public function getNodes($node = null, $orderBy = 'sort', $direction = 'asc')
    {
        $dql = $this->childrenQueryBuilder($node, true, $orderBy, $direction);
        $dql->leftJoin('node.parent', 'tree_parent');
        return $dql->getQuery()->getResult();
    }

    public function getFormFillArray(): array
    {
        return $this->_build($this->getNodes());
    }

    private function _build($tree, $level = 1): array
    {
        $ret = [];

        foreach ($tree as $item) {
            $ret[$item->getId()] = str_repeat("&nbsp;", ($level - 1) * 3) . $item->getTitle();
            if (count($item->getChildren()) > 0) {
                $ret += $this->_build($item->getChildren(), $item->getLevel() + 1);
            }
        }
        return $ret;
    }

}