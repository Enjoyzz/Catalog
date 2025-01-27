<?php

declare(strict_types=1);

namespace EnjoysCMS\Module\Catalog;

use DI\Container;
use DI\DependencyException;
use DI\NotFoundException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\NotSupported;
use Enjoys\Session\Session;
use EnjoysCMS\Core\ContentEditor\EditorConfig;
use EnjoysCMS\Core\Modules\AbstractModuleConfig;
use EnjoysCMS\Core\StorageUpload\StorageUploadInterface;
use EnjoysCMS\Module\Catalog\Admin\Product\Images\ThumbnailService;
use EnjoysCMS\Module\Catalog\Admin\Product\Images\ThumbnailService\ThumbnailServiceInterface;
use EnjoysCMS\Module\Catalog\Entity\Currency\Currency;
use EnjoysCMS\Module\Catalog\Entity\Setting;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final class Config extends AbstractModuleConfig
{

    private ?array $dbConfig = null;

    public function __construct(
        \Enjoys\Config\Config $config,
        private readonly Container $container,
        private readonly Session $session,
        private readonly EntityManager $em,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct($config);
    }

    public function getModulePackageName(): string
    {
        return 'enjoyscms/catalog';
    }

    /**
     * @throws NotSupported
     */
    private function fetchDbConfig(): array
    {
        return $this->em->getRepository(Setting::class)->findAllKeyVar();
    }

    public function getCurrentCurrencyCode(): string
    {
        return $this->session->get('catalog')['currency'] ?? $this->get(
            'currency->default'
        ) ?? throw new InvalidArgumentException(
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


    public function getWishlistSortMode(): ?string
    {
        return $this->session->get('catalog')['wishlist_sort'] ?? $this->get(
            'wishlist_sort'
        );
    }

    public function setWishlistMode(string $mode = null): void
    {
        $this->session->set([
            'catalog' => array_merge(
                $this->session->get('catalog', []),
                ['wishlist_sort' => $mode],
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

    public function getWishlistPerPage(): string
    {
        return (string)($this->session->get('catalog')['wishlistLimitItems'] ?? $this->get('wishlistLimitItems', 12));
    }

    public function setWishlistPerPage(string $perpage = null): void
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
                ['wishlistLimitItems' => $perpage],
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
     * @deprecated
     */
    public function getThumbnailService(): ThumbnailServiceInterface
    {
        return $this->container->get($this->get('thumbnailService'));
    }

    /**
     * @throws DependencyException
     * @throws NotFoundException
     */
    public function getThumbnailCreationService(): ?ThumbnailService
    {
        $classString = $this->get(
            'thumbnailCreationService',
            ThumbnailService\DefaultThumbnailCreationService::class
        );

        try {
            if ($classString === null) {
                return null;
            }
            return $this->container->get($classString);
        } catch (DependencyException|NotFoundException) {
            $this->logger->warning(
                sprintf(
                    'ThumbnailService %s not found. Use %s',
                    $classString,
                    ThumbnailService\DefaultThumbnailCreationService::class
                )
            );
            return $this->container->get(ThumbnailService\DefaultThumbnailCreationService::class);
        }
    }

    public function getAdminTemplatePath(): string
    {
        try {
            $templatePath = getenv('ROOT_PATH') . $this->get(
                    'admin->template_dir',
                    throw new InvalidArgumentException()
                );
        } catch (InvalidArgumentException) {
            $templatePath = __DIR__ . '/../template/admin';
        }

        $realpath = realpath($templatePath);

        if ($realpath === false) {
            throw new InvalidArgumentException(
                sprintf(
                    'Template admin path is invalid: %s. Check parameter `admin->template_dir` in config',
                    $templatePath
                )
            );
        }
        return $realpath;
    }

    public function getEditorConfigProductDescription(): array|string|null|EditorConfig
    {
        return $this->get('admin->editor->productDescription');
    }

    public function getEditorConfigCategoryDescription(): array|string|null|EditorConfig
    {
        return $this->get('admin->editor->categoryDescription');
    }

    public function getEditorConfigCategoryShortDescription(): array|string|null|EditorConfig
    {
        return $this->get('admin->editor->categoryShortDescription');
    }

    public function getDefaultPriceGroup(): string
    {
        return $this->get('priceGroup->default', 'ROZ');
    }

    public function getOldPriceGroupName(): string
    {
        return $this->get('priceGroup->oldPriceGroupName', 'OLD');
    }

    public function getDelimiterOptions(string $default = '|'): string
    {
        return $this->get('delimiterOptions') ?? $default;
    }

    /**
     * @throws NotSupported
     */
    public function getSearchOptionField(): string
    {
        $this->dbConfig ??= $this->fetchDbConfig();
        return $this->dbConfig['searchOptionField'] ?? '';
    }

    /**
     * @throws NotSupported
     */
    public function getGlobalExtraFields(): string
    {
        $this->dbConfig ??= $this->fetchDbConfig();
        return $this->dbConfig['globalExtraFields'] ?? '';
    }

    public function getEditorConfigAdvancedGroupOptionEdit(): array|string|null|EditorConfig
    {
        return $this->get('admin->editor->advancedGroupOptionEdit');
    }

}
