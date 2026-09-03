<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Database\Eloquent\Collection;

/**
 * Ekran użytkowników: podsumowanie i lista w pasmach.
 *
 * Trzy grupy zamiast jednej płaskiej listy, bo znaczą co innego:
 * konto pracujące, konto założone i nigdy nieużyte, konto wyłączone.
 * W starym systemie wszystkie trzy leżały razem, więc nie dało się
 * zobaczyć, że ktoś ma dostęp, którego nigdy nie odebrał.
 */
final readonly class UserBoardService
{
    /** Po tylu dniach bez logowania konto trafia do „wymaga uwagi”. */
    public const STALE_DAYS = 90;

    /**
     * @return array<string, mixed>
     */
    public function board(?Carbon $now = null): array
    {
        $today = $now ?? Carbon::now();

        /** @var Collection<int, User> $users */
        $users = User::query()
            ->with(['roles', 'location'])
            ->orderByRaw('last_login_at IS NULL')
            ->orderByDesc('last_login_at')
            ->orderBy('first_name')
            ->get();

        $active = [];
        $invited = [];
        $disabled = [];

        foreach ($users as $user) {
            $row = $this->row($user, $today);

            if (!$user->is_active) {
                $disabled[] = $row;
                continue;
            }

            // Konto bez ani jednego logowania to zaproszenie, które nikt
            // nie odebrał — nie to samo co konto nieużywane od miesięcy.
            if ($user->last_login_at === null) {
                $invited[] = $row;
                continue;
            }

            $active[] = $row;
        }

        return [
            'summary' => $this->summary($active, $invited, $disabled, $today),
            'groups' => [
                ['key' => 'active', 'rows' => $active],
                ['key' => 'invited', 'rows' => $invited],
                ['key' => 'disabled', 'rows' => $disabled],
            ],
        ];
    }

    /**
     * Zaproszenie to link do ustawienia hasła.
     *
     * Zakładanie konta nadaje losowe hasło, którego nikt nie zna, więc
     * bez tego kroku nowy użytkownik nie ma jak wejść do systemu.
     *
     * @return array{sent: bool, message: string}
     */
    public function invite(User $user): array
    {
        if (!$user->is_active) {
            return ['sent' => false, 'message' => 'Konto jest wyłączone — najpierw je włącz.'];
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            return ['sent' => false, 'message' => 'Zaproszenie wysłano niedawno — spróbuj za chwilę.'];
        }

        if ($status !== Password::RESET_LINK_SENT) {
            return ['sent' => false, 'message' => 'Nie udało się wysłać zaproszenia.'];
        }

        return ['sent' => true, 'message' => 'Zaproszenie wysłane na ' . $user->email . '.'];
    }

    /**
     * @param list<array<string, mixed>> $active
     * @param list<array<string, mixed>> $invited
     * @param list<array<string, mixed>> $disabled
     * @return array<string, mixed>
     */
    private function summary(array $active, array $invited, array $disabled, Carbon $today): array
    {
        $loggedInToday = 0;
        $stale = 0;

        foreach ($active as $row) {
            if ($row['logged_in_today'] === true) {
                ++$loggedInToday;
            }

            if ($row['is_stale'] === true) {
                ++$stale;
            }
        }

        $oldestInviteDays = null;

        foreach ($invited as $row) {
            $days = $row['waiting_days'];

            if (is_int($days) && ($oldestInviteDays === null || $days > $oldestInviteDays)) {
                $oldestInviteDays = $days;
            }
        }

        /** @var list<string> $roleNames */
        $roleNames = Role::query()->orderBy('name')->pluck('name')->all();

        return [
            'active' => count($active),
            'logged_in_today' => $loggedInToday,
            'invited' => count($invited),
            'oldest_invite_days' => $oldestInviteDays,
            'disabled' => count($disabled),
            'roles' => count($roleNames),
            'role_names' => $roleNames,
            'stale' => $stale,
            'stale_days' => self::STALE_DAYS,
            'total' => count($active) + count($invited) + count($disabled),
            'as_of' => $today->toDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user, Carbon $today): array
    {
        $lastLogin = $user->last_login_at;
        $isStale = $user->is_active
            && $lastLogin !== null
            && $lastLogin->diffInDays($today) >= self::STALE_DAYS;

        $createdAt = $this->rawDate($user, 'created_at');

        /** @var list<string> $roleNames */
        $roleNames = $user->roles->pluck('name')->all();

        // Identyfikatory rol, a nie tylko nazwy: formularz edycji musi
        // wiedziec, co zaznaczyc. Bez tego zapis czyscilby role, ktore
        // uzytkownik ma teraz.
        $roleIds = [];

        foreach ($user->roles as $role) {
            $roleIds[] = (int) $role->getKey();
        }

        return [
            'id' => (int) $user->id,
            'name' => trim($user->first_name . ' ' . ($user->last_name ?? '')),
            'initials' => $this->initials($user),
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $user->location?->name,
            'roles' => $roleNames,
            'role_ids' => $roleIds,
            // Rola nadrzędna omija sprawdzanie uprawnień, więc widok
            // musi to pokazać wprost, a nie chować za nazwą roli.
            'is_superuser' => $user->roles->contains(
                static fn(Role $role): bool => (bool) ($role->getAttribute('is_superuser') ?? false),
            ),
            'is_active' => (bool) $user->is_active,
            'is_self' => (int) $user->id === (int) Auth::id(),
            'last_login_at' => $lastLogin?->toIso8601String(),
            'logged_in_today' => $lastLogin !== null && $lastLogin->isSameDay($today),
            'is_stale' => $isStale,
            'waiting_days' => $lastLogin === null
                ? (int) $createdAt?->diffInDays($today)
                : null,
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }

    /**
     * Salvon zamienia `created_at` na sformatowany string w akcesorze,
     * wiec do liczenia dni trzeba siegnac po surowa wartosc z bazy.
     * `last_login_at` ma jawne rzutowanie i wraca jako Carbon.
     */
    private function rawDate(User $user, string $column): ?Carbon
    {
        $raw = $user->getRawOriginal($column);

        if ($raw === null || $raw === '') {
            return null;
        }

        return Carbon::parse((string) $raw);
    }

    private function initials(User $user): string
    {
        $parts = array_filter([$user->first_name, $user->last_name]);
        $initials = '';

        foreach ($parts as $part) {
            $initials .= mb_strtoupper(mb_substr((string) $part, 0, 1));
        }

        return $initials === '' ? '?' : mb_substr($initials, 0, 2);
    }
}
