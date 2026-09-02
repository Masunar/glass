<?php

namespace Salvon\Bundle\Media\Controller;

use Exception;
use Salvon\Controller\ApiController;
use Salvon\Bundle\Media\Model\Media;
use Salvon\Bundle\Media\Service\MediaService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class MediaController extends ApiController
{
    public function __construct(
        private readonly MediaService $service,
    ) {}

    /**
     * @throws Exception
     */
    public function getMedia(string $uuid, ?string $variant = null): BinaryFileResponse
    {

        /** @var Media $media */
        $media = Media::modelQuery()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->fileResponse($this->service->execute($media, $variant));
    }

    public function download(string $uuid, ?string $variant = null): BinaryFileResponse
    {

        /** @var Media $media */
        $media = Media::modelQuery()
            ->where('uuid', $uuid)
            ->firstOrFail();

        return $this->downloadResponse($this->service->execute($media, $variant));
    }
}
