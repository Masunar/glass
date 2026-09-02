<?php

declare(strict_types=1);

namespace Salvon\Repository;

use Illuminate\Http\Request;
use Override;
use Salvon\Contract\DataTransferObject;

readonly class QueryParam implements DataTransferObject
{
    public function __construct(
        public ?int    $page,
        public ?int    $limit,
        public ?string $orderBy,
        public ?string $order,
        public ?string $searchQuery,
        public array   $otherParams = [],
    ) {}

    #[Override]
    public function toArray(): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'orderBy' => $this->orderBy,
            'order' => $this->order,
            'searchQuery' => $this->searchQuery,
            'otherParams' => $this->otherParams,
        ];
    }

    public static function fromRequest(Request $request, int $defaultLimit = 25, int $maxLimit = 500): QueryParam
    {
        $page = (int) $request->query->get('page', 0);
        $limit = (int) $request->query->get('limit', $defaultLimit);
        $orderBy = (string) $request->query->get('order_by');
        $order = (string) $request->query->get('order', 'desc');
        $searchQuery = (string) $request->query->get('search_query', '');
        $otherParams = collect($request->query->all())
            ->except(['page', 'limit', 'order_by', 'order', 'search_query'])
            ->toArray();

        if ($limit < 1 && -1 != $limit) {
            $limit = $defaultLimit;
        }

        if ($limit > $maxLimit) {
            $limit = $maxLimit;
        }

        return new QueryParam(
            $page,
            $limit,
            $orderBy,
            $order,
            $searchQuery,
            $otherParams,
        );
    }
}
