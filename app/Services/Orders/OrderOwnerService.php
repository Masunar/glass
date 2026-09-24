<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\User;
use App\Models\Order;
use App\Enum\Permission;
use Salvon\Enum\SubPermission;
use App\Services\AuditTrail;
use Illuminate\Database\Eloquent\Collection;

/**
 * Prowadzący zlecenie — kogo pytać o nie dzisiaj.
 *
 * **Odpowiedzialność, nie dostęp.** Każdy, kto widzi zlecenia, widzi
 * wszystkie: handlowiec musi odebrać telefon w sprawie zlecenia kolegi,
 * a produkcja nie pyta, czyj to klient. Prowadzący mówi wyłącznie, kto
 * odpowiada — i dlatego nie zawęża ani listy, ani karty.
 *
 * **Domyślnie zakładający, ale zmienialny.** Zlecenie przechodzi między
 * ludźmi: urlop, zmiana opiekuna klienta, przekazanie na montaż. Pole
 * przypisane raz na zawsze skończyłoby się tym, że po pół roku
 * inicjały na liście nie znaczą nic.
 *
 * **Prowadzącym może być tylko ktoś, kto widzi zlecenia.** Wskazanie
 * magazyniera nie jest decyzją, tylko cichym zgubieniem sprawy: nie
 * zobaczy jej ani na liście, ani na pulpicie.
 */
final readonly class OrderOwnerService
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * Kandydaci na prowadzącego: aktywne konta z dostępem do zleceń.
     *
     * @return list<array{id: int, name: string}>
     */
    public function candidates(): array
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->with(['roles.permissions', 'permissions'])
            ->where('is_active', true)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $rows = [];

        foreach ($users as $user) {
            if (!$user->can(Permission::ORDERS->value . '.' . SubPermission::LIST->value)) {
                continue;
            }

            $rows[] = [
                'id' => (int) $user->getKey(),
                'name' => self::name($user),
            ];
        }

        return $rows;
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function change(int $orderId, mixed $userId): array
    {
        /** @var Order $order */
        $order = Order::query()->with('owner')->findOrFail($orderId);

        $id = is_numeric($userId) ? (int) $userId : 0;

        if ($id <= 0) {
            return ['errors' => ['owner_id' => ['Wskaż prowadzącego.']]];
        }

        /** @var User|null $user */
        $user = User::query()->with(['roles.permissions', 'permissions'])->find($id);

        if ($user === null || !$user->is_active) {
            return ['errors' => ['owner_id' => ['Takiego konta nie ma albo jest wyłączone.']]];
        }

        // Ta sama regula, co przy liscie kandydatow — lista jest
        // podpowiedzia, a nie zabezpieczeniem: zadanie moze przyjsc
        // z pominieciem ekranu.
        if (!$user->can(Permission::ORDERS->value . '.' . SubPermission::LIST->value)) {
            return ['errors' => ['owner_id' => ['To konto nie widzi zleceń, więc nie może ich prowadzić.']]];
        }

        $before = $order->owner;

        if ($before !== null && (int) $before->getKey() === $id) {
            return ['errors' => []];
        }

        $order->owner_id = $id;
        $order->save();

        // Przekazanie zlecenia jest decyzja, nie poprawka literowki:
        // po tygodniu ktos zapyta, od kiedy to jego sprawa.
        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [[
                'field' => 'prowadzący',
                'before' => $before === null ? null : self::name($before),
                'after' => self::name($user),
            ]],
            'owner_changed',
        );

        return ['errors' => []];
    }

    public static function name(User $user): string
    {
        $name = trim((string) $user->first_name . ' ' . (string) $user->last_name);

        return $name === '' ? (string) $user->email : $name;
    }

    /** Inicjały prowadzącego — dwa znaki przy kolumnie „co dalej". */
    public static function initials(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $initials = '';

        foreach (array_filter([$user->first_name, $user->last_name]) as $part) {
            $initials .= mb_strtoupper(mb_substr((string) $part, 0, 1));
        }

        return $initials === '' ? null : mb_substr($initials, 0, 2);
    }
}
