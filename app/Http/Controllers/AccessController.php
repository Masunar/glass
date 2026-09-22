<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Access\AccessBoard;
use App\Services\Access\AccessService;

/**
 * Role, uprawnienia, paczki i odstępstwa przy użytkowniku.
 *
 * Paczki chodzą na `PERMISSIONS`, a nie na `ROLES`: zmiana paczki
 * dotyka wszystkich ról, które ją mają, więc to inna decyzja niż
 * ustawienie jednej roli — i ma dać się nadać komu innemu.
 */
class AccessController extends ApiController
{
    public function __construct(
        private readonly AccessBoard $board,
        private readonly AccessService $service,
    ) {
        $this->protect(
            ['roles', 'role'],
            Permission::ROLES->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['saveRole'],
            Permission::ROLES->value,
            SubPermission::UPDATE->value,
        );
        $this->protect(
            ['package'],
            Permission::PERMISSIONS->value,
            SubPermission::LIST->value,
        );
        $this->protect(
            ['savePackage', 'deletePackage'],
            Permission::PERMISSIONS->value,
            SubPermission::UPDATE->value,
        );
        // Odstepstwo przy uzytkowniku jest zmiana jego dostepu, wiec
        // chodzi na uprawnieniu do kont, nie do rol.
        $this->protect(
            ['user'],
            Permission::USERS->value,
            SubPermission::READ->value,
        );
        $this->protect(
            ['saveUser'],
            Permission::USERS->value,
            SubPermission::UPDATE->value,
        );
    }

    public function roles(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->roles()));
    }

    public function role(int $role): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->board->role($role)));
    }

    public function saveRole(Request $request, int $role): JsonResponse
    {
        return $this->secure(function () use ($request, $role): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->saveRole($role, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            // Bilans wraca do ekranu: „zapisano" nie mowi nic
            // o operacji, po ktorej chce sie wiedziec, co sie zmienilo.
            return $this->dataResponse(['balance' => $result['balance']]);
        });
    }

    /** Zawartosc paczki albo pusty formularz nowej (`package` = null). */
    public function package(?int $package = null): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->package($package),
        ));
    }

    public function user(int $user): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse(
            $this->board->user($user),
        ));
    }

    public function saveUser(Request $request, int $user): JsonResponse
    {
        return $this->secure(function () use ($request, $user): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->saveUser($user, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['balance' => $result['balance']]);
        });
    }

    public function savePackage(Request $request, ?int $package = null): JsonResponse
    {
        return $this->secure(function () use ($request, $package): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->savePackage($package, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse(['id' => $result['id'], 'balance' => $result['balance']]);
        });
    }

    public function deletePackage(int $package): JsonResponse
    {
        return $this->secure(function () use ($package): JsonResponse {
            $result = $this->service->deletePackage($package);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->deletedResponse();
        });
    }
}
