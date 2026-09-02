<?php

declare(strict_types=1);

namespace Salvon\Imagicker\DTO;

final readonly class Dimensions
{
    public function __construct(
        public ?int $width = null,
        public ?int $height = null,
    ) {}

    public function toString(string $separator = 'x', $widthFirst = true): string
    {
        $firstDimension = $widthFirst ? $this->width : $this->height;
        $secondDimensions = $widthFirst ? $this->height : $this->width;

        return sprintf(
            '%s %s %s',
            $separator,
            $firstDimension,
            $secondDimensions,
        );
    }
}
