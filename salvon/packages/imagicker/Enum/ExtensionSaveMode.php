<?php

declare(strict_types=1);

namespace Salvon\Imagicker\Enum;

enum ExtensionSaveMode: int
{
    case FROM_ORIGINAL_MIME_TYPE = 1;
    case FROM_GIVEN_PATH = 2;
    case FROM_GIVEN_PATH_WITH_FORMAT_UPDATE = 3;
}
