<?php

declare(strict_types=1);

namespace App\Listeners;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Auth\Events\Login;

final readonly class AuthListener
{
    public function handle(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;
        $user->update(['last_login_at' => Carbon::now()]);
    }
}
