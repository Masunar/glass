<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Salvon\Service\Str;
use Salvon\Facade\Error;
use App\Mail\PasswordChangedEmail;
use App\DTO\Auth\ResetPasswordDTO;
//use Salvon\Geolocalization\IpLookup;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\PasswordReset;

final readonly class ResetPasswordService
{
    public function forgotPassword(ResetPasswordDTO $resetPasswordDTO): void
    {
        $user = User::query()->where(['email' => $resetPasswordDTO->email])->first();

        if (!($user instanceof User)) {
            return;
        }

        $status = Password::sendResetLink(['email' => $resetPasswordDTO->email]);

        if ($status === Password::RESET_THROTTLED) {
            Error::throttling();
        }
    }

    public function resetPassword(ResetPasswordDTO $resetPasswordDTO): void
    {
        $status = Password::reset($resetPasswordDTO->toArray(), function (User $user, string $password) {
            $user
                ->forceFill(['password' => Hash::make($password)])
                ->setRememberToken(Str::random(60))
            ;

            $user->save();

            event(new PasswordReset($user));

            $ip = request()->ip();

            //$geo = IpLookup::search($ip);

            PasswordChangedEmail::send([
                'user' => $user->toArray(),
                'date' => now()->format('Y-m-d: H:i:s'),
                'ip' => $ip,
                //'location' => [
                //    'city' => $geo->city,
                //    'region' => $geo->region,
                //    'country' => $geo->country,
                //],
            ]);
        });

        if ($status === Password::PASSWORD_RESET) {
            return;
        }

        if ($status === Password::INVALID_TOKEN) {
            Error::badRequest('invalid_token');
        }

        if ($status === Password::INVALID_USER) {
            Error::badRequest('invalid_user');
        }

        Error::ise('Password change attempt failed.');
    }
}
