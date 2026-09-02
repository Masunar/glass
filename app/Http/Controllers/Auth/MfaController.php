<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Services\Auth\Mfa;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;

final class MfaController extends ApiController
{
    public function requirement(): JsonResponse
    {
        if (!self::hasMfa()) {
            return $this->unauthorizedResponse();
        }

        if (!Mfa::requestLocked(user())) {
            return $this->dataResponse(['type' => null]);
        }

        return $this->dataResponse(['type' => user()->mfa->type->value]);
    }

    public function verify(Request $request): JsonResponse
    {
        if (!self::hasMfa()) {
            return $this->unauthorizedResponse();
        }

        $mfaCode = $request->post('code');

        if (Mfa::requestLocked(user()) && Mfa::verify(user(), $mfaCode)) {
            Mfa::unlockRequests();

            return $this->successResponse();
        }

        return $this->invalidMfaResponse();
    }

    public function recoveryAccount(Request $request): JsonResponse
    {
        if (!self::hasMfa()) {
            return $this->unauthorizedResponse();
        }

        $recoveryKey = $request->post('recovery_key');

        if (Mfa::requestLocked(user()) && Mfa::verifyRecoveryKey(user(), $recoveryKey)) {
            Mfa::unlockRequests();
            Mfa::disable(user());

            return $this->successResponse();
        }

        return $this->invalidMfaResponse();
    }

    public function beginSetup(): JsonResponse
    {
        if (!authenticated()) {
            return $this->unauthorizedResponse();
        }

        return $this->dataResponse(Mfa::beginSetup(user()));
    }

    public function finishSetup(Request $request): JsonResponse
    {
        if (!self::hasMfa()) {
            return $this->unauthorizedResponse();
        }

        $recoveryKey = Mfa::finishSetup($request->post('code'), user());

        if ($recoveryKey === null) {
            return $this->invalidMfaResponse();
        }

        return $this->dataResponse(['recovery_key' => $recoveryKey]);
    }

    public function recoveryKey(): JsonResponse
    {
        if (!self::hasMfa()) {
            return $this->unauthorizedResponse();
        }

        return $this->dataResponse(['recovery_key' => user()->mfa->recovery_key]);
    }

    public function disable(): JsonResponse
    {
        if (!authenticated()) {
            return $this->unauthorizedResponse();
        }

        Mfa::disable(user());

        return $this->successResponse();
    }

    public function beginEmailSetup(): JsonResponse
    {
        if (!authenticated()) {
            return $this->unauthorizedResponse();
        }

        Mfa::beginEmailSetup(user());

        return $this->successResponse();
    }

    public function finishEmailSetup(Request $request): JsonResponse
    {
        if (!authenticated()) {
            return $this->unauthorizedResponse();
        }

        $code = $request->post('code');

        if (Mfa::finishEmailSetup(user(), $code)) {
            return $this->successResponse();
        }

        return $this->invalidMfaResponse();
    }

    private static function hasMfa(): bool
    {
        return authenticated() && !is_null(user()->mfa);
    }
}
