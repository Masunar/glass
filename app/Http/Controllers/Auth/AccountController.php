<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\AccountService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;

final class AccountController extends ApiController
{
    public function __construct(
        private readonly AccountService $accountService,
    ) {}

    public function changePassword(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            if (!authenticated()) {
                return $this->unauthorizedResponse();
            }

            if (!$this->accountService->changePassword($request, user())) {
                return $this->badRequestResponse('invalid_current_password');
            }

            return $this->successResponse();
        });
    }

    public function updateProfile(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            if (!authenticated()) {
                return $this->unauthorizedResponse();
            }

            $emailChanged = $this->accountService->updateProfile($request, user());

            return $this->updatedResponse(data: ['email_changed' => $emailChanged]);
        });
    }
}
