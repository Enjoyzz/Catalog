<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog\Admin\Setting;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Enjoys\Forms\AttributeFactory;
use Enjoys\Forms\Form;
use EnjoysCMS\Module\Catalog\Entity\OptionKey;
use EnjoysCMS\Module\Catalog\Entity\Setting;
use Psr\Http\Message\ServerRequestInterface;

final class SettingForm
{

    private EntityRepository $settingRepository;

    /**
     * @throws NotSupported
     */
    public function __construct(
        private readonly EntityManager $em,
        private readonly ServerRequestInterface $request,
    ) {
        $this->settingRepository = $this->em->getRepository(Setting::class);
    }


    public function getForm(): Form
    {
        $form = new Form();

        $form->setDefaults(function () {
            $setting = $this->settingRepository->findAll();
            $defaults = [];
            /** @var Setting $item */
            foreach ($setting as $item) {
                $defaults[$item->getKey()] = explode(',', $item->getValue());
            }

            return $defaults;
        });

//        $form->number('minSearchChars', 'minSearchChars');

        $form->select(
            'globalExtraFields',
            "Опции (глобально) для отображения во всех группах товаров и товарах"
        )
            ->setAttribute(AttributeFactory::create('id', 'globalExtraFields'))
            ->setDescription(
                'Дополнительные поля, которые можно отображать в списке продуктов,а также в короткой информации о товаре.
             Берутся из параметров товара (опций)'
            )
            ->setMultiple()->fill(function () {
                $value = $this->settingRepository->findOneBy(['key' => 'globalExtraFields'])?->getValue();
                if ($value === null) {
                    return [];
                }
                $optionKeys = $this->em->getRepository(OptionKey::class)->findBy(
                    [
                        'id' => explode(',', $value)
                    ]
                );
                $result = [];
                foreach ($optionKeys as $key) {
                    $result[$key->getId()] = [
                        $key->getName() . (($key->getUnit()) ? ' (' . $key->getUnit() . ')' : ''),
                        ['id' => uniqid()]
                    ];
                }
                return $result;
            });

        $form->select(
            'searchOptionField',
            "Опции по которым также будет идти поиск"
        )
            ->setAttribute(AttributeFactory::create('id', 'searchOptionField'))
            ->setDescription(
                'Опции по которым также будет идти поиск, наряду с названием, описанием и названием категории.
                Берутся из параметров товара (опций)'
            )
            ->setMultiple()
            ->fill(function () {
                $value = $this->settingRepository->findOneBy(['key' => 'searchOptionField'])?->getValue();
                if ($value === null) {
                    return [];
                }
                $optionKeys = $this->em->getRepository(OptionKey::class)->findBy(
                    [
                        'id' => explode(',', $value)
                    ]
                );
                $result = [];
                foreach ($optionKeys as $key) {
                    $result[$key->getId()] = [
                        $key->getName() . (($key->getUnit()) ? ' (' . $key->getUnit() . ')' : ''),
                        ['id' => uniqid()]
                    ];
                }
                return $result;
            });
        $form->submit('save', 'Сохранить');
        return $form;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function doAction(): void
    {
        foreach ($this->settingRepository->findAll() as $item) {
            $this->em->remove($item);
        }
        $this->em->flush();

        foreach ($this->request->getParsedBody() as $key => $value) {
            switch ($key) {
                case 'minSearchChars':
                case 'searchOptionField':
                case 'globalExtraFields':
                    if (is_array($value)) {
                        $value = implode(',', $value);
                    }
                    $setting = $this->settingRepository->findOneBy(['key' => $key]);
                    if ($setting === null) {
                        $setting = new Setting();
                    }
                    $setting->setKey($key);
                    $setting->setValue($value);
                    $this->em->persist($setting);

                    break;
            }
        }
        $this->em->flush();
    }
}