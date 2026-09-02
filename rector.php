<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->importNames();
    $rectorConfig->cacheDirectory('./storage/framework/rector/cache');
    $rectorConfig->paths([
        'app/',
        'config/',
        //'salvon/',
        'database/',
        'routes/api/',
        'tests/',
    ]);

    $rectorConfig->skip([
        'salvon/src/routes/api.php',
    ]);

    $rectorConfig->sets([
        SetList::PHP_84,
        SetList::CODING_STYLE,
        SetList::CODE_QUALITY,
        SetList::STRICT_BOOLEANS,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
    ]);
};
