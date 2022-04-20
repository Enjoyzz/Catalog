<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Crud\PriceGroup;


use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use Enjoys\Forms\Exception\ExceptionRule;
use Enjoys\Forms\Form;
use Enjoys\Forms\Interfaces\RendererInterface;
use Enjoys\Forms\Rules;
use Enjoys\ServerRequestWrapper;
use EnjoysCMS\Core\Components\Helpers\Redirect;
use EnjoysCMS\Module\Admin\Core\ModelInterface;
use EnjoysCMS\Module\Catalog\Entities\PriceGroup;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PriceGroupEdit implements ModelInterface
{


    private PriceGroup $priceGroup;

    public function __construct(
        private EntityManager $em,
        private ServerRequestWrapper $requestWrapper,
        private RendererInterface $renderer,
        private UrlGeneratorInterface $urlGenerator
    ) {

        $priceGroup = $this->em->getRepository(PriceGroup::class)->find($this->requestWrapper->getQueryData('id'));
        if ($priceGroup === null){
            throw new NoResultException();
        }
        $this->priceGroup = $priceGroup;
    }

    public function getContext(): array
    {
        $form = $this->getAddForm();
        if ($form->isSubmitted()) {
            $this->doAction();
        }
        $this->renderer->setForm($form);
        return [
            'form' => $this->renderer->output()
        ];
    }

    /**
     * @throws ExceptionRule
     */
    private function getAddForm(): Form
    {
        $form = new Form();
        $form->setDefaults([
            'code' => $this->priceGroup->getCode(),
            'title' => $this->priceGroup->getTitle(),
        ]);
        $form->text('code', 'Идентификатор цены (внутренний), например ROZ, OPT и тд')
            ->addRule(Rules::REQUIRED)
            ->addRule(Rules::CALLBACK, 'Такой код уже существует', function () {
                $pg = $this->em->getRepository(PriceGroup::class)->findOneBy(
                    ['code' => $this->requestWrapper->getPostData('code')]
                );

                if($pg === null){
                    return true;
                }

                if ($pg->getId() === $this->priceGroup->getId()){
                    return true;
                }

                return false;
            });

        $form->text('title', 'Наименование');
        $form->submit('add');
        return $form;
    }

    private function doAction(): void
    {

        $this->priceGroup->setTitle($this->requestWrapper->getPostData('title'));
        $this->priceGroup->setCode($this->requestWrapper->getPostData('code'));
        $this->em->flush();
        Redirect::http($this->urlGenerator->generate('catalog/admin/pricegroup'));
    }
}
