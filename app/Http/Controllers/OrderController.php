<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Orders\OrderBoardService;

/**
 * Lista zleceń — pasma pilności zamiast sortowania.
 */
class OrderController extends ApiController
{
    public function __construct(
        private readonly OrderBoardService $board,
    ) {
        $this->protect(['board'], Permission::ORDERS->value, SubPermission::LIST->value);
    }

    public function board(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $query = $request->query('q');
            $status = $request->query('status');

            return $this->dataResponse($this->board->board(
                is_string($query) ? $query : null,
                is_string($status) ? $status : null,
            ));
        });
    }
}
