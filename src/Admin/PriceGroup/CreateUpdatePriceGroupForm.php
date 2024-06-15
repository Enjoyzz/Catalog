<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\PriceGroup;


use Doctrine\ORM\EntityManagerInterface;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Rules;
use EnjoysCMS\Module\Catalog\Entity\PriceGroup;
use Psr\Http\Message\ServerRequestInterface;

final class CreateUpdatePriceGroupForm
{

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ServerRequestInterface $request,
    ) {
    }


    /**
     * @throws ExceptionRule
     */
    public function getForm(PriceGroup $priceGroup = null): Form
    {
        $form = new Form();
        $form->setDefaults([
            'code' => $priceGroup?->getCode(),
            'title' => $priceGroup?->getTitle(),
        ]);
        $form->text('code', 'Идентификатор цены (внутренний), например ROZ, OPT и тд')
            ->addRule(Rules::REQUIRED)
            ->addRule(Rules::CALLBACK, 'Такой код уже существует', function () use ($priceGroup) {
                $pg = $this->em->getRepository(PriceGroup::class)->findOneBy(
                    ['code' => $this->request->getParsedBody()['code'] ?? null]
                );

                if ($pg === null) {
                    return true;
                }

                if ($pg->getId() === $priceGroup?->getId()) {
                    return true;
                }

                return false;
            });

        $form->text('title', 'Наименование');
        $form->submit('add');
        return $form;
    }

    public function doAction(PriceGroup $priceGroup = null): void
    {
        $priceGroup = $priceGroup ?? new PriceGroup();
        $priceGroup->setTitle($this->request->getParsedBody()['title'] ?? null);
        $priceGroup->setCode($this->request->getParsedBody()['code'] ?? null);
        $this->em->persist($priceGroup);
        $this->em->flush();
    }
}
