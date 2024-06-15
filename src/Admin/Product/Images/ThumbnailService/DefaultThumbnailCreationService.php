<?php

declare(strict_types=1);


namespace EnjoysCMS\Module\Catalog\Admin\Product\Images\ThumbnailService;


use EnjoysCMS\Module\Catalog\Admin\Product\Images\ThumbnailService;
use EnjoysCMS\Module\Catalog\Config;
use Intervention\Image\Exception\NotReadableException;
use Intervention\Image\ImageManagerStatic;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use RuntimeException;

final class DefaultThumbnailCreationService implements ThumbnailService
{

    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @throws FilesystemException
     */
    public function make(string $location, FilesystemOperator $filesystem): void
    {
        $data = $filesystem->read($location);

        $this->checkMemory($data);

        $filesystem->write(
            str_replace(
                '.',
                '_small.',
                $location
            ),
            $this->generate($data, [
                'resize' => [
                    300,
                    300,
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                ]
            ])
        );

        $filesystem->write(
            str_replace(
                '.',
                '_large.',
                $location
            ),
            $this->generate($data, [
                'resize' => [
                    900,
                    900,
                    function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    }
                ]
            ])
        );
    }

    private function generate($data, array $params = []): string
    {
        try {
            $image = ImageManagerStatic::make($data);
            foreach ($params as $method => $param) {
                $image->$method(...$param);
            }
            return $image->encode()->getEncoded();
        } catch (NotReadableException) {
            return $data;
        }
    }

    private function checkMemory(string $fileContent): void
    {
        $memoryLimit = (int)ini_get('memory_limit') * pow(1024, 2);
        $imageInfo = getimagesizefromstring($fileContent);

        if ($imageInfo === false) {
            return;
        }

        $memoryNeeded = round(
            ($imageInfo[0] * $imageInfo[1] * ($imageInfo['bits'] ?? 1) * ($imageInfo['channels'] ?? 1) / 8 + Pow(
                    2,
                    16
                )) * 1.65
        );
        if (function_exists('memory_get_usage') && memory_get_usage() + $memoryNeeded > $memoryLimit) {
            if (!$this->config->get('allocatedMemoryDynamically')) {
                throw new RuntimeException(
                    sprintf(
                        'The allocated memory (%s MiB) is not enough for image processing. Needed: %s MiB',
                        $memoryLimit / pow(1024, 2),
                        ceil((memory_get_usage() + $memoryNeeded) / pow(1024, 2))
                    )
                );
            }

            ini_set(
                'memory_limit',
                (integer)ini_get('memory_limit') + ceil(
                    (memory_get_usage() + $memoryNeeded) / pow(1024, 2)
                ) . 'M'
            );
        }
    }


}
