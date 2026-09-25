<?php

declare(strict_types=1);

namespace App\Services\Orders;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Order;
use App\Support\Normalize;
use App\Services\AuditTrail;
use Illuminate\Support\Facades\Auth;

/**
 * Zgoda administratora na produkcję mimo przekroczonego limitu kupieckiego.
 *
 * Limit blokuje przejście do produkcji zawsze — zaliczka już go nie
 * omija (decyzja Marcina, 25.09). Wyjątkiem jest zgoda **na konkretnym
 * zleceniu**, dana przez administratora, z powodem. Zgoda nie jest
 * cicha: karta pokazuje, kto, kiedy i dlaczego, a dziennik ma wpis
 * przy udzieleniu i przy cofnięciu.
 *
 * „Administrator" to rola nadrzędna (`isSuperUser`). Osobne uprawnienie
 * byłoby czymś, co da się nadać przez pomyłkę razem z paczką.
 */
final readonly class OrderCreditOverride
{
    public function __construct(
        private AuditTrail $audit = new AuditTrail(),
    ) {
    }

    public static function allowed(?User $user): bool
    {
        return $user !== null && $user->isSuperUser();
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function grant(int $orderId, mixed $reason): array
    {
        $user = Auth::user();

        if (!$user instanceof User || !self::allowed($user)) {
            return ['errors' => ['reason' => ['Zgodę na przekroczenie limitu daje administrator.']]];
        }

        $text = Normalize::text(is_string($reason) ? $reason : null);

        if ($text === null) {
            return ['errors' => ['reason' => ['Podaj powód — zgoda bez powodu nie mówi, dlaczego limit nie zadziałał.']]];
        }

        if (mb_strlen($text) > 300) {
            return ['errors' => ['reason' => ['Powód zmieści się w 300 znakach.']]];
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        $order->credit_override_by = (int) $user->getKey();
        $order->credit_override_at = Carbon::now();
        $order->credit_override_reason = $text;
        $order->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'zgoda mimo limitu kupieckiego', 'before' => null, 'after' => $text]],
            'credit_override_granted',
        );

        return ['errors' => []];
    }

    /**
     * @return array{errors: array<string, list<string>>}
     */
    public function revoke(int $orderId): array
    {
        $user = Auth::user();

        if (!$user instanceof User || !self::allowed($user)) {
            return ['errors' => ['reason' => ['Zgodę cofa administrator.']]];
        }

        /** @var Order $order */
        $order = Order::query()->findOrFail($orderId);

        if ($order->credit_override_at === null) {
            return ['errors' => []];
        }

        $before = $order->credit_override_reason;

        $order->credit_override_by = null;
        $order->credit_override_at = null;
        $order->credit_override_reason = null;
        $order->save();

        $this->audit->write(
            Order::class,
            (int) $order->getKey(),
            [['field' => 'zgoda mimo limitu kupieckiego', 'before' => $before, 'after' => null]],
            'credit_override_revoked',
        );

        return ['errors' => []];
    }
}
