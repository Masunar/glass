<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\DashboardService;

/**
 * Pulpit.
 *
 * Bez `protect()` — pulpit jest dostępny każdemu zalogowanemu, tak jak
 * jego strona w rejestrze (`AccessRegistry::PAGES['index']` bez
 * uprawnienia). **Przycinanie robi usługa**, sekcja po sekcji, bo
 * jedno uprawnienie nie opisałoby ekranu, który zbiera dane z czterech
 * modułów naraz.
 */
class DashboardController extends ApiController
{
    public function __construct(
        private readonly DashboardService $board,
    ) {
        // Bez `parent::__construct()`: `ApiController` go nie ma, a inne
        // kontrolery wolaja go tylko wtedy, gdy dziedzicza po
        // `ApiCrudController`.
    }

    public function board(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $user = $request->user();

            if (!$user instanceof User) {
                return $this->unauthorizedResponse();
            }

            return $this->dataResponse($this->board->board($user));
        });
    }
}
