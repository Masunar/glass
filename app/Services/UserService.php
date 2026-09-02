<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Salvon\Repository\RepositoryResult;
use Salvon\Service\Str;
use Spatie\Permission\Models\Role;

final readonly class UserService
{
    public function list(Request $request, bool $includeTrashed = false): RepositoryResult
    {
        return repo_list($request, User::class, [
            'trashed' => $includeTrashed,
            'filters' => [
                'name' => 'search:own:like:or',
                'last_name' => 'search:own:like:or',
                'email' => 'search:own:like:or',
            ],
            'modify' => function ($qb) use ($request) {
                if ($request->has('role_id')) {
                    $qb->whereHas('roles', fn($q) => $q->where('roles.id', $request->integer('role_id')));
                }
            },
            'with' => ['roles'],
        ]);
    }

    public function create(Request $request): User
    {
        crud_validate($request, [
            'first_name' => 'required',
            'email' => 'email|unique:users,email',
            'roles' => 'required|array',
        ]);

        $pass = Str::password();

        $entity = new User([
            'first_name' => $request->post('first_name'),
            'last_name' => $request->post('last_name'),
            'email' => $request->post('email'),
            'phone' => $request->post('phone'),
            'is_active' => (bool) $request->post('is_active'),
            'password' => Hash::make($pass),
        ]);

        $entity->save();

        $entity->roles()->sync($request->post('roles'));
        return $entity;
    }

    public function update(Request $request, User $entity): void
    {
        crud_validate($request, [
            'first_name' => 'required',
            'email' => 'email|unique:users,email,' . $entity->id,
            'roles' => 'nullable|array',
        ]);

        $entity->update([
            'first_name' => $request->post('first_name'),
            'last_name' => $request->post('last_name'),
            'email' => $request->post('email'),
            'phone' => $request->post('phone'),
            'is_active' => (bool) $request->post('is_active'),
        ]);

        $entity->roles()->sync($request->post('roles', []));
    }

    public function roles(Request $request): RepositoryResult
    {
        return repo_list($request, Role::class);
    }
}
