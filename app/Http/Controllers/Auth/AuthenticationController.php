<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use Salvon\Enum\AuthState;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\DTO\Auth\ResetPasswordDTO;
use App\DTO\Auth\AuthenticationDTO;
use Illuminate\Support\Facades\App;
use Salvon\Controller\ApiController;
use App\Services\Auth\ResetPasswordService;
use App\Services\Auth\AuthenticationService;
use App\Validators\Auth\AuthenticationValidator;

final class AuthenticationController extends ApiController
{
    public function __construct(
        private readonly AuthenticationService $authenticationService,
        private readonly AuthenticationValidator $validator,
        private readonly ResetPasswordService $resetPasswordService,
    ) {}

    public function login(Request $request): JsonResponse
    {
        $mode = $request->query->get('mode') ?? 'cookie';
        return $this->authenticate($request, $mode === 'token');
    }

    public function logout(): JsonResponse
    {
        logout();

        return $this->successResponse();
    }

    public function user(): JsonResponse
    {
        if (authenticated()) {
            return $this->userDataResponse();
        }

        return $this->unauthorizedResponse();
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $dto = ResetPasswordDTO::from($request->getContent());
            $this->resetPasswordService->forgotPassword($dto);
            return $this->successResponse();
        });
    }

    public function resetPassword(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $dto = ResetPasswordDTO::from($request->getContent());
            $this->resetPasswordService->resetPassword($dto);
            return $this->successResponse();
        });
    }

    private function authenticate(Request $request, bool $useTokenAuthentication): JsonResponse
    {
        return $this->secure(function () use ($request, $useTokenAuthentication): JsonResponse {
            if (authenticated()) {
                return $this->userDataResponse();
            }

            $dto = AuthenticationDTO::from($request->getContent());

            if (!$this->validator->violations($dto)) {
                return $this->validationResponse(
                    $this->validator->getErrors(),
                );
            }

            return match ($this->authenticationService->attempt($dto)) {
                AuthState::INACTIVE => $this->badRequestResponse('account_inactive'),
                AuthState::MFA_REQUIRED => $this->mfaRequiredResponse(),
                AuthState::MFA_INVALID => $this->badRequestResponse('mfa_invalid'),
                AuthState::UNAUTHENTICATED => $this->badRequestResponse('invalid_credentials'),
                AuthState::AUTHENTICATED => $this->returnAuthenticationState($useTokenAuthentication),
            };
        });
    }

    private function userDataResponse(): JsonResponse
    {
        return $this->dataResponse(
            $this->getUserData(),
        );
    }

    private function getUserData(): array
    {
        $user = user();

        if (!$user->activated()) {
            self::logout();
            App::abort(401);
        }

        return [
            ...$user->toArray(),
            'permissions' => $user->getAllPermissions(),
            'is_super_user' => $user->isSuperUser(),
            'roles' => $user->getRoleNames(),
            'mfa_type' => $user->mfa?->type?->value,
            'mfa_active' => $user->mfaActive(),
        ];
    }

    private function returnAuthenticationState(bool $useTokenAuthentication): JsonResponse
    {
        $user = user();

        if (!$useTokenAuthentication) {
            return $this->userDataResponse();
        }

        return $this->dataResponse([
            'user' => $this->getUserData(),
            'token' => $user->generateAuthenticationToken()->plainTextToken,
        ]);
    }
}
