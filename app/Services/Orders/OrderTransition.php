<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\Order;
use App\DTO\Orders\NextStep;
use App\Services\AuditTrail;
use App\Models\StatusTransition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Wykonanie przejścia statusu.
 *
 * Warunki są sprawdzane **drugi raz**, tutaj. Ekran pokazuje przycisk
 * tylko przy przejściu dostępnym, ale między narysowaniem listy a
 * kliknięciem mija czas, w którym ktoś inny mógł wstrzymać listę albo
 * zgłosić reklamację. Stary system pozwalał wybrać dowolny status
 * z rozwijanej listy i nie sprawdzał niczego — stąd zlecenia
 * w produkcji bez zaliczki.
 *
 * Zmiana statusu zawsze zostawia ślad w dzienniku: „kto, kiedy, z czego
 * na co". Bez tego nie da się odpowiedzieć, dlaczego zlecenie stoi.
 */
final readonly class OrderTransition
{
    /**
     * Warunek, który da się spełnić treścią podaną razem z akcją.
     * Anulowanie wymaga powodu — i powód przychodzi z okna anulowania,
     * a nie z osobnego formularza edycji zlecenia.
     */
    private const REASON_RULE = 'cancellation_reason_set';

    public function __construct(
        private OrderNextStep $nextStep = new OrderNextStep(),
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    /**
     * @return array{errors: list<string>, status: string|null, status_code: string|null}
     */
    public function run(int $orderId, int $transitionId, ?string $reason = null): array
    {
        /** @var Order $order */
        $order = Order::query()
            ->with(['status', 'contractor', 'lists.items.processes'])
            ->findOrFail($orderId);

        /** @var StatusTransition|null $transition */
        $transition = StatusTransition::query()
            ->where('id', $transitionId)
            ->where('from_status_id', $order->status_id)
            ->where('is_active', true)
            ->first();

        // Przejście spoza bieżącego statusu to nie błąd walidacji, tylko
        // nieaktualny ekran: ktoś przesunął zlecenie w międzyczasie.
        if ($transition === null) {
            return $this->fail('To przejście nie jest już dostępne — zlecenie zmieniło status.');
        }

        $permission = $transition->required_permission;

        if ($permission !== null && $permission !== '') {
            $user = Auth::user();

            if ($user === null || !$user->can($permission)) {
                return $this->fail('Nie masz uprawnienia do tego przejścia.');
            }
        }

        if ($reason !== null && $reason !== '' && $this->acceptsReason($transition)) {
            $order->cancellation_reason = $reason;
            $order->save();
            $order->refresh();
        }

        $step = $this->stepFor($order, $transitionId);

        if ($step === null) {
            return $this->fail('To przejście nie jest już dostępne — zlecenie zmieniło status.');
        }

        if (!$step->available) {
            return $this->fail($step->blockedBy ?? 'Warunki przejścia nie są spełnione.');
        }

        $target = $step->target;
        $before = $order->status?->code;

        DB::transaction(function () use ($order, $target, $before): void {
            $order->status_id = (int) $target->getKey();
            $order->save();

            $this->audit->write(
                Order::class,
                (int) $order->getKey(),
                [['field' => 'status', 'before' => $before, 'after' => $target->code]],
                'status_changed',
            );
        });

        return ['errors' => [], 'status' => $target->name, 'status_code' => $target->code];
    }

    private function acceptsReason(StatusTransition $transition): bool
    {
        foreach ($transition->conditions ?? [] as $condition) {
            if (($condition['rule'] ?? null) === self::REASON_RULE) {
                return true;
            }
        }

        return false;
    }

    private function stepFor(Order $order, int $transitionId): ?NextStep
    {
        foreach ($this->nextStep->forOrder($order) as $step) {
            if ($step->transitionId === $transitionId) {
                return $step;
            }
        }

        return null;
    }

    /**
     * @return array{errors: list<string>, status: string|null, status_code: string|null}
     */
    private function fail(string $message): array
    {
        return ['errors' => [$message], 'status' => null, 'status_code' => null];
    }
}
