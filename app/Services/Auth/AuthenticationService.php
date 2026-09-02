<?php

namespace App\Services\Auth;

use App\Models\User;
use Salvon\Facade\Auth;
use Salvon\Enum\AuthState;
use App\DTO\Auth\AuthenticationDTO;

class AuthenticationService
{
    public function attempt(AuthenticationDTO $dto): AuthState
    {
        if (Auth::authenticated()) {
            return AuthState::AUTHENTICATED;
        }

        $user = $this->fromCredentials($dto);

        if (!$user instanceof User) {
            return AuthState::UNAUTHENTICATED;
        }

        if (!$user->activated()) {
            return AuthState::INACTIVE;
        }

        Auth::attempt([
            'email' => $dto->email,
            'password' => $dto->password,
        ], $dto->rememberMe);

        if (Mfa::active($user)) {
            Mfa::requestMfa($user);
            return AuthState::MFA_REQUIRED;
        }

        return AuthState::AUTHENTICATED;
    }

    public function fromCredentials(AuthenticationDTO $dto): ?User
    {
        $isValid = Auth::validate([
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        if (!$isValid) {
            return null;
        }

        return User::query()->where([
            'email' => $dto->email,
        ])->first();
    }
}
