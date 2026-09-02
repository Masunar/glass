<?php

declare(strict_types=1);

namespace Salvon\Imagicker\DTO;

use Salvon\Imagicker\Enum\Extension;

final readonly class ProcessingFilter
{
    public function __construct(
        public ?Extension $extension = null,
        public ?int       $width = null,
        public ?int       $height = null,
        public ?int       $quality = null,
        public ?bool      $removeBackground = null,
    ) {}
}
