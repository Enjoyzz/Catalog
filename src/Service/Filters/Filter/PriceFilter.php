<?php

namespace EnjoysCMS\Module\Catalog\Service\Filters\Filter;

use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Elements\Number;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Config;
use EnjoysCMS\Module\Catalog\Entity\PriceGroup;
use EnjoysCMS\Module\Catalog\Entity\ProductPrice;
use EnjoysCMS\Module\Catalog\Helpers\Normalize;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterInterface;
use EnjoysCMS\Module\Catalog\Service\Filters\FilterParams;

class PriceFilter implements FilterInterface
{

    private string $name = 'Цена';

    private ?array $possibleValues = null;

    public function __construct(
        private EntityManager $em,
        private Config $config,
        private FilterParams $params
    ) {
    }


    public function __toString(): string
    {
        return $this->getName();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getPossibleValues(array $productIds): array
    {
        if ($this->possibleValues !== null) {
            return $this->possibleValues;
        }

        $minmaxRawQb = $this->em->createQueryBuilder();
        if (!in_array($this->em->getConnection()->getDatabasePlatform()::class, [SqlitePlatform::class])) {
            $minmaxRawQb->select(
                'MIN(CONVERT_PRICE(pr.price, pr.currency, :current_currency)) as min, MAX(CONVERT_PRICE(pr.price, pr.currency, :current_currency)) as max'
            );
        } else {
            $minmaxRawQb->select(
                'MIN(pr.price) as min, MAX(pr.price) as max'
            );
        }
        $minmaxRawQb->from(ProductPrice::class, 'pr')
            ->where('pr.product IN (:pids)')
            ->setParameters([
                'pids' => $productIds,
                'current_currency' => $this->config->getCurrentCurrencyCode()
            ]);

        $this->possibleValues = array_map(function ($value) {
            return (int)ceil(Normalize::intPriceToFloat($value));
        },
            $minmaxRawQb->getQuery()
                ->getOneOrNullResult());

        return $this->possibleValues;
    }

    /**
     * @throws NotSupported
     */
    public function addFilterQueryBuilderRestriction(QueryBuilder $qb): QueryBuilder
    {
        return $qb->andWhere(
            $qb->expr()->between(
                'CONVERT_PRICE(pr.price, pr.currency, :current_currency)',
                ':minPrice',
                ':maxPrice'
            )
        )
            ->andWhere('pr.priceGroup = :priceGroup')
            ->setParameter('current_currency', $this->config->getCurrentCurrencyCode())
            ->setParameter(
                'priceGroup',
                $this->em->getRepository(PriceGroup::class)->findOneBy(['code' => $this->config->getDefaultPriceGroup()]
                )
            )
            ->setParameter('minPrice', Normalize::floatPriceToInt($this->params->currentValues['min']))
            ->setParameter('maxPrice', Normalize::floatPriceToInt($this->params->currentValues['max']));
    }

    public function getFormElement(Form $form, $values): Form
    {
        $min = $values['min'] === null ? 0 : $values['min'] - 1;
        $max = $values['max'] === null ? $min + 1 : $values['max'] + 1;

        $form->setDefaults([
            'filter' => [
                'price' => [
                    'min' => $min,
                    'max' => $max,
                ]
            ]
        ]);

        $form->group($this->__toString())
            ->addClass('slider-group')
            ->add([
                (new Number('filter[price][min]'))
                    ->addClass('minInput')
                    ->addAttribute(
                        AttributeFactory::create(
                            'placeholder',
                            sprintf('от %s', $this->config->getCurrentCurrency()->format($min))
                        )
                    )
                    ->setMin($min)
                    ->setMax($max),
                (new Number('filter[price][max]'))
                    ->addClass('maxInput')
                    ->addAttribute(
                        AttributeFactory::create(
                            'placeholder',
                            sprintf('до %s', $this->config->getCurrentCurrency()->format($max))
                        )
                    )
                    ->setMin($min)
                    ->setMax($max)
                ,
            ]);

        return $form;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
