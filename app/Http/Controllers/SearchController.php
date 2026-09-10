<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\SearchService;
use Salvon\Controller\ApiController;

/**
 * Wyszukiwanie ogólne — jedno pole na całą aplikację.
 *
 * Kontroler nie ma własnego uprawnienia: każda grupa wyników pilnuje
 * swojego, więc handlowiec dostaje kontrahentów, ale nie użytkowników.
 */
class SearchController extends ApiController
{
    public function __construct(
        private readonly SearchService $service,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse([
            'groups' => $this->service->search((string) $request->query('q', '')),
        ]));
    }
}
