<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use App\Models\User;
use Salvon\Database\Seeder;
use Database\Seeders\Core\RoleSeeder;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

/**
 * Konta deweloperskie.
 *
 * **Jedno konto to za mało na dane próbne.** Przy samym administratorze
 * każde zlecenie ma tego samego prowadzącego, więc filtr „tylko moje"
 * nie zmienia ani jednego wiersza, a inicjały przy cudzych sprawach nie
 * pokazują się nigdy. Ekran wygląda wtedy na zepsuty, choć działa —
 * dokładnie tak, jak pierwsza wersja pulpitu w jednoosobowym biurze.
 *
 * Drugie konto jest też jedynym sposobem, żeby przejść aplikację rolą
 * nie-administracyjną bez zakładania konta ręcznie: rola nadrzędna omija
 * sprawdzanie uprawnień w całości, więc administrator nigdy nie zobaczy
 * tego, co zobaczy handlowiec.
 *
 * ⚠️ **Nazwiska są umowne** — to konta próbne, nie osoby z firmy.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $this->createAdmin();

        // Rozne role, bo roznia sie nie tylko nazwa: „Zlecenia —
        // obsluga" z paczek startowych daje dostep do zlecen, a bez
        // niego konto nie moze byc prowadzacym.
        $this->account(RoleSeeder::SENIOR_SALES, 'Piotr', 'Nowak', 'piotr@synteco.pl');
        $this->account(RoleSeeder::SALES, 'Anna', 'Kowalska', 'anna@synteco.pl');
    }

    public function createAdmin(): void
    {
        $user = $this->account(RoleSeeder::ADMINISTRATOR, 'Administrator', '', 'admin@synteco.pl');

        $user->tokens()->create([
            'name' => 'admin_token',
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'token' => hash('sha256', 'admin_token'),
        ]);

        echo "\n  Development purpose admin token: 'admin_token' \n\n";
    }

    private function account(string $roleName, string $first, string $last, string $email): User
    {
        // Role zaklada Core\RoleSeeder - sa danymi referencyjnymi,
        // a nie deweloperskimi, bo odwoluja sie do nich limity rabatowe.
        $role = Role::query()->where('name', $roleName)->firstOrFail();

        $envEmail = getenv('SEEDER_ADMIN_EMAIL');
        $envPass = getenv('SEEDER_ADMIN_PW');

        // Adres z otoczenia dotyczy wylacznie administratora — to nim
        // sie logujemy. Pozostale konta maja adresy stale, bo sluza do
        // przelaczania sie miedzy rolami, a nie do odbierania poczty.
        if ($roleName === RoleSeeder::ADMINISTRATOR && !empty($envEmail)) {
            $email = $envEmail;
        }

        $password = !empty($envPass) ? $envPass : 'secret';

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => $first,
            'last_name' => $last === '' ? null : $last,
            'is_active' => true,
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'phone' => '+48 123 456 789',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user->roles()->attach($role);

        return $user;
    }
}
