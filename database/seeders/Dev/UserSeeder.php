<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use App\Models\User;
use Salvon\Database\Seeder;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();
    }

    public function createAdmin(): void
    {
        $role = new Role(['name' => 'Administrator']);
        $role->forceFill(['is_superuser' => true]);
        $role->save();

        $envEmail = getenv('SEEDER_ADMIN_EMAIL');
        $envPass = getenv('SEEDER_ADMIN_PW');

        $email = !empty($envEmail) ? $envEmail : 'admin@synteco.pl';
        $password = !empty($envPass) ? $envPass : 'secret';

        $user = User::query()->create([
            'first_name' => 'Administrator',
            'is_active' => true,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'phone' => '+48 123 456 789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->roles()->attach($role);

        $user->tokens()->create([
            'name' => 'admin_token',
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'token' => hash('sha256', 'admin_token'),
        ]);

        echo "\n  Development purpose admin token: 'admin_token' \n\n";
    }
}
