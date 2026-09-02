<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final readonly class AccountService
{
    public function changePassword(Request $request, User $user): bool
    {
        crud_validate($request, [
            'current_password' => 'required',
            'password' => 'required|min:8',
            'password_confirmation' => 'required',
        ]);

        if ($request->post('password') !== $request->post('password_confirmation')) {
            return false;
        }

        if (!Hash::check($request->post('current_password'), $user->password)) {
            return false;
        }

        $user->update([
            'password' => Hash::make($request->post('password')),
        ]);

        return true;
    }

    public function updateProfile(Request $request, User $user): bool
    {
        crud_validate($request, [
            'first_name' => 'required',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $emailChanged = $request->post('email') !== $user->email;

        $user->update([
            'first_name' => $request->post('first_name'),
            'last_name' => $request->post('last_name'),
            'email' => $request->post('email'),
            'phone' => $request->post('phone'),
        ]);

        return $emailChanged;
    }
}
