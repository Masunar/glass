<?php

namespace Salvon\BDO\Parser;

use Salvon\BDO\DTO\WasteProcess;
use Salvon\BDO\DTO\WasteProcesses;

final readonly class WasteProcessesParser
{
    public static function execute(string $content): ?WasteProcesses
    {
        $data = json_decode($content, true);

        if(!$data){
            return null;
        }

        return new WasteProcesses(self::parseItems($data));
    }

    private static function parseItems(array $items): array
    {
        return array_map(function (array $item){
            return new WasteProcess(
                id: (int)($item['waste_code_id'] ?? 0),
                name: $item['name'] ?? '',
                parent: $item['parent'] ?? '',
                codeName: $item['code_name'] ?? '',
                type: $item['type'] ?? '',
            );
        }, $items);
    }
}
