<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\RegonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Salvon\Controller\ApiController;

final class RegonController extends ApiController
{
    public function __construct(
        private readonly RegonService $service,
    ) {}

    public function findByNip(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $data = $this->service->findByNip($request);

            return $this->dataResponse($data);
        });
    }
}
