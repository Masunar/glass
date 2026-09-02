<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User as Model;
use App\Enum\Permission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiCrudController;
use Salvon\Enum\SubPermission;
use App\Services\UserService as CrudService;

class UserController extends ApiCrudController
{
    protected ?string $permission = Permission::USERS->value;

    public function __construct(
        private readonly CrudService $crudService,
    ) {
        parent::__construct();
        $this->protect(['roles'], $this->permission, SubPermission::READ->value);
    }

    public function list(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            return $this->repositoryListResponse(
                $request,
                $this->crudService->list($request),
            );
        });
    }

    public function create(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $this->crudService->create($request);
            return $this->createdResponse();
        });
    }

    public function update(Request $request, Model $entity): JsonResponse
    {
        return $this->secure(function () use ($request, $entity): JsonResponse {
            $this->crudService->update($request, $entity);
            return $this->updatedResponse();
        });
    }

    public function delete(Model $entity): JsonResponse
    {
        return $this->secure(function () use ($entity): JsonResponse {
            $entity->deleteOrFail();
            return $this->deletedResponse();
        });
    }

    public function restore(Model $entity): JsonResponse
    {
        return $this->secure(function () use ($entity): JsonResponse {
            $entity->restore();
            return $this->successResponse();
        });
    }

    public function roles(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            return $this->repositoryListResponse(
                $request,
                $this->crudService->roles($request),
            );
        });
    }
}
