<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Enjoys\Session\Session;
use EnjoysCMS\Core\StorageUpload\StorageUploadInterface;
use EnjoysCMS\Module\Catalog\Crud\Images\ThumbnailService\ThumbnailServiceInterface;
use EnjoysCMS\Module\Catalog\Entities\Currency\Currency;
use InvalidArgumentException;
use RuntimeException;

final class Config
{

    private const CONFIG_SECTION = 'enjoyscms/catalog';


    public function __construct(
        private \Enjoys\Config\Config $config,
        private Container $container,
        private Session $session,
        private EntityManager $em
    ) {
    }

    public function get(string $key = null, mixed $default = null): mixed
    {
        if ($key === null){
            return $this->config->get(self::CONFIG_SECTION);
        }
        return $this->config->get(sprintf('%s->%s', self::CONFIG_SECTION, $key), $default);
    }


    public function all(): array
    {
        return $this->config->get();
    }


    public function getCurrentCurrencyCode(): string
    {
        return $this->session->get('catalog')['currency'] ?? $this->get(
            'currency->default') ?? throw new InvalidArgumentException(
            'Default currency value not valid'
        );
    }

    /**
     * @throws NotSupported
     */
    public function getCurrentCurrency(): Currency
    {
        return $this->em->getRepository(Currency::class)->find(
            $this->getCurrentCurrencyCode()
        ) ?? throw new InvalidArgumentException(
            'Default currency value not valid'
        );
    }

    public function setCurrencyCode(?string $code): void
    {
        $this->session->set([
            'catalog' => array_merge(
                $this->session->get('catalog', []),
                ['currency' => $code],
            )
        ]);
    }

    public function getSortMode(): ?string
    {
        return $this->session->get('catalog')['sort'] ?? $this->get(
            'sort'
        );
    }

    public function setSortMode(string $mode = null): void
    {
        $this->session->set([
            'catalog' => array_merge(
                $this->session->get('catalog', []),
                ['sort' => $mode],
            )
        ]);
    }


    public function getPerPage(): string
    {
        return (string)($this->session->get('catalog')['limitItems'] ?? $this->get(
            'limitItems'
        ) ?? throw new InvalidArgumentException('limitItems not set'));
    }

    public function setPerPage(string $perpage = null): void
    {
        $allowedPerPage = $this->get(
            'allowedPerPage'
        ) ?? throw new InvalidArgumentException('allowedPerPage not set');

        if (!in_array((int)$perpage, $allowedPerPage, true)) {
            return;
        }

        $this->session->set([
            'catalog' => array_merge(
                $this->session->get('catalog', []),
                ['limitItems' => $perpage],
            )
        ]);
    }

    public function getImageStorageUpload($storageName = null): StorageUploadInterface
    {
        $storageName = $storageName ?? $this->get('productImageStorage');

        $storageUploadConfig = $this->get(sprintf('storageUploads->%s', $storageName)) ?? throw new RuntimeException(
            sprintf('Not set config `storageUploads->%s`', $storageName)
        );
        /** @var class-string $storageUploadClass */
        $storageUploadClass = key($storageUploadConfig);
        return new $storageUploadClass(...current($storageUploadConfig));
    }

    public function getFileStorageUpload($storageName = null): StorageUploadInterface
    {
        $storageName = $storageName ?? $this->get('productFileStorage');

        $storageUploadConfig = $this->get(sprintf('storageUploads->%s', $storageName)) ?? throw new RuntimeException(
            sprintf('Not set config `storageUploads->%s`', $storageName)
        );
        /** @var class-string $storageUploadClass */
        $storageUploadClass = key($storageUploadConfig);
        return new $storageUploadClass(...current($storageUploadConfig));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getThumbnailService(): ThumbnailServiceInterface
    {
        return $this->container->get($this->get('thumbnailService'));
    }

    public function getAdminTemplatePath(): string
    {
        try {
            $templatePath = getenv('ROOT_PATH') . $this->get(
                    'adminTemplateDir',
                    throw new InvalidArgumentException()
                );
        } catch (InvalidArgumentException) {
            $templatePath = __DIR__ . '/../template/admin';
        }

        $realpath = realpath($templatePath);

        if ($realpath === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Template admin path is invalid: %s. Check parameter `adminTemplateDir` in config',
                    $templatePath
                )
            );
        }
        return $realpath;
    }

    public function getEditorConfigProductDescription()
    {
        return $this->get('editor->productDescription');
    }

    public function getEditorConfigCategoryDescription()
    {
        return $this->get('editor->categoryDescription');
    }

    public function getEditorConfigCategoryShortDescription()
    {
        return $this->get('editor->categoryShortDescription');
    }

}
