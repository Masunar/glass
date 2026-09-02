<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Carbon\Carbon;
use App\Models\User;
use Salvon\MFA\TOTP;
use App\Enum\MfaType;
use App\Mail\UserMfaEmail;
use Salvon\Facade\Session;
use App\Mail\UserMfaSetupEmail;

final readonly class Mfa
{
    public const string GLOBAL_MFA_REQUIREMENT_KEY = 'global_mfa_requirement';

    public static function verify(User $user, string $code, bool $pending = false): bool
    {
        if (!$pending && !$user->mfaActive()) {
            return false;
        }

        $code = self::sanitizeCode($code);

        if ($user->mfa->type === MfaType::TOTP) {
            return self::verifyTotp($user, $pending, $code);
        }

        return self::verifyEmail($user, $pending, $code);
    }

    public static function verifyRecoveryKey(User $user, string $recoveryKey): bool
    {
        if (!$user->mfaActive() || empty($user->mfa->recovery_key)) {
            return false;
        }

        return hash_equals($user->mfa->recovery_key, self::sanitizeCode($recoveryKey));
    }

    public static function requestMfa(User $user): void
    {
        if (!$user->mfaActive()) {
            return;
        }

        Session::store(self::GLOBAL_MFA_REQUIREMENT_KEY, Carbon::now()->format('Y-m-d H:i:s'));

        if (!in_array($user->mfa->type, [MfaType::EMAIL, /* MfaType::SMS */])) {
            return;
        }

        $code = TOTP::recoveryCode(1, 8, '');
        $code = strtoupper($code);
        $user->mfa->update(['code_last_issued_at' => now(), 'code' => $code]);

        UserMfaEmail::send(['code' => $code, 'user' => $user->only(['email', 'first_name'])], enqueue: false);
    }

    public static function unlockRequests(): void
    {
        Session::forget(self::GLOBAL_MFA_REQUIREMENT_KEY);
    }

    public static function requestLocked(User $user): bool
    {
        if (!$user->mfaActive()) {
            self::unlockRequests();
            return false;
        }

        return !empty(Session::get(self::GLOBAL_MFA_REQUIREMENT_KEY));
    }

    public static function beginSetup(User $user, MfaType $type = MfaType::TOTP): array
    {
        $secret = TOTP::secret();
        $url = TOTP::url(config('salvon.mfa.app_name'), user()->email, $secret);

        $user->updateOrCreateMfa(['pending_value' => $secret, 'type' => $type]);

        return ['code' => $secret, 'url' => $url, 'type' => $type];
    }

    public static function finishSetup(string $code, User $user): ?string
    {
        $valid = self::verify($user, $code, true);

        if (!$valid) {
            return null;
        }

        $recoveryKey = TOTP::recoveryCode();
        $user->mfa->update([
            'secret'        => $user->mfa->pending_value,
            'pending_value' => null,
            'is_active'     => true,
            'recovery_key'  => $recoveryKey,
        ]);

        return $recoveryKey;
    }

    public static function disable(User $user): void
    {
        $user->mfa?->delete();
    }

    public static function beginEmailSetup(User $user): void
    {
        $code = strtoupper(TOTP::recoveryCode(1, 8, ''));
        $user->updateOrCreateMfa([
            'type'               => MfaType::EMAIL,
            'code'               => $code,
            'code_last_issued_at' => now(),
            'is_active'          => false,
        ]);

        UserMfaSetupEmail::send(['code' => $code, 'user' => $user->only(['email', 'first_name'])], enqueue: false);
    }

    public static function finishEmailSetup(User $user, string $code): bool
    {
        if (!$user->mfa || $user->mfa->type !== MfaType::EMAIL) {
            return false;
        }

        $code = self::sanitizeCode($code);
        $issuedAt = $user->mfa->code_last_issued_at?->clone()->addMinutes(5);

        if (!$issuedAt || now()->gt($issuedAt) || $code !== $user->mfa->code) {
            return false;
        }

        $user->mfa->update(['is_active' => true, 'code' => null]);

        return true;
    }

    public static function active(User $user): bool
    {
        return $user->mfaActive();
    }

    private static function sanitizeCode(string $code): string
    {
        return strip_tags(trim($code));
    }

    private static function verifyTotp(User $user, bool $pending, string $code): bool
    {
        $secret = $pending ? $user->mfa->pending_value : $user->mfa->secret;

        return TOTP::verify($code, $secret);
    }

    private static function verifyEmail(User $user, bool $pending, string $code): bool
    {
        $userCode = $pending ? $user->mfa->pending_value : $user->mfa->code;

        if (!$user->mfa->code_last_issued_at) {
            return false;
        }

        $issuedAt = $user->mfa->code_last_issued_at->clone()->addMinutes(5);

        return now()->lte($issuedAt) && $code === $userCode;
    }
}
