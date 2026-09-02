<?php

namespace Salvon\BDO\Parser;

use Salvon\BDO\DTO\WasteCode;
use Salvon\BDO\DTO\WasteCodes;

final readonly class WasteCodesParser
{
    public static function execute(string $content): ?WasteCodes
    {
        $data = json_decode($content, true);

        if(!$data){
            return null;
        }

        return new WasteCodes(self::parseItems($data));
    }

    private static function parseItems(array $items): array
    {
        return array_map(function (array $item){
            return new WasteCode(
                id: (int)($item['waste_code_id'] ?? 0),
                code: $item['code'] ?? '',
                description: $item['description'] ?? '',
                isDangerous: (bool)($item['is_dangerous'] ?? false),
            );
        }, $items);
    }
}
