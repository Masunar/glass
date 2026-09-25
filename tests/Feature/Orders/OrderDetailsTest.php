<?php

declare(strict_types=1);

namespace Tests\Feature\Orders;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Status;
use App\Models\AuditEntry;
use App\Enum\StatusDomain;
use App\Models\Contractor;
use App\Enum\ContractorType;
use App\Enum\DeliveryMethod;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderDetailsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Termin i komentarze poprawiane z karty zlecenia.
 */
class OrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    private OrderDetailsService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new OrderDetailsService();
    }

    private function order(): Order
    {
        /** @var Status $status */
        $status = Status::findByCode(StatusDomain::ORDER, 'ZLECENIE');

        /** @var Contractor $contractor */
        $contractor = Contractor::query()->create([
            'type' => ContractorType::COMPANY->value,
            'name' => 'Karta ' . random_int(1000, 9999),
        ]);

        /** @var Order */
        return Order::query()->create([
            'number' => random_int(90000, 99999),
            'contractor_id' => $contractor->id,
            'status_id' => $status->id,
            'delivery_method' => DeliveryMethod::PICKUP->value,
            'client_deadline' => '2026-10-10',
        ]);
    }

    private function fresh(Order $order): Order
    {
        /** @var Order */
        return Order::query()->findOrFail($order->getKey());
    }

    #[Test]
    public function przesuniecie_zapisuje_sie_z_powodem_i_trafia_do_dziennika(): void
    {
        $order = $this->order();

        $result = $this->service->deadline((int) $order->getKey(), [
            'client_deadline' => '2026-10-10',
            'shifted_deadline' => '2026-10-17',
            'shift_reason' => 'brak szkła u dostawcy',
        ]);

        $fresh = $this->fresh($order);

        $this->assertSame([], $result['errors']);
        $this->assertSame('2026-10-10', $fresh->client_deadline?->toDateString());
        $this->assertSame('2026-10-17', $fresh->shifted_deadline?->toDateString());
        $this->assertSame('brak szkła u dostawcy', $fresh->shift_reason);

        /** @var AuditEntry $entry */
        $entry = AuditEntry::query()->where('event', 'deadline_changed')->firstOrFail();

        // Termin klienta sie nie zmienil, wiec w dzienniku go nie ma —
        // wpis mowi, co przesunieto, a nie przepisuje calego formularza.
        $this->assertSame(
            ['termin przesunięty', 'powód przesunięcia'],
            array_column($entry->changes ?? [], 'field'),
        );
    }

    #[Test]
    public function powod_bez_przesuniecia_nie_zostaje(): void
    {
        $order = $this->order();

        $this->service->deadline((int) $order->getKey(), [
            'client_deadline' => '2026-10-12',
            'shifted_deadline' => '',
            'shift_reason' => 'zostawiony z poprzedniej wersji',
        ]);

        // Powod bez przesuniecia bylby na karcie zdaniem o przesunieciu,
        // ktorego nie ma.
        $this->assertNull($this->fresh($order)->shift_reason);
        $this->assertSame('2026-10-12', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function zla_data_jest_odrzucana_i_niczego_nie_zmienia(): void
    {
        $order = $this->order();

        $result = $this->service->deadline((int) $order->getKey(), ['client_deadline' => '12.10.2026']);

        $this->assertArrayHasKey('client_deadline', $result['errors']);
        $this->assertSame('2026-10-10', $this->fresh($order)->client_deadline?->toDateString());
    }

    #[Test]
    public function komentarz_dla_produkcji_zapisuje_sie_a_pusty_czysci(): void
    {
        $order = $this->order();

        $this->assertSame([], $this->service->comment((int) $order->getKey(), 'production', '  fazę 10 mm  ')['errors']);
        $this->assertSame('fazę 10 mm', $this->fresh($order)->production_comment);

        $this->service->comment((int) $order->getKey(), 'production', '');

        // Pusty tekst to brak komentarza, nie komentarz z samych spacji.
        $this->assertNull($this->fresh($order)->production_comment);
        $this->assertSame(2, AuditEntry::query()->where('event', 'comment_changed')->count());
    }

    #[Test]
    public function krotka_uwaga_ma_limit_kolumny(): void
    {
        $order = $this->order();

        $result = $this->service->comment((int) $order->getKey(), 'short', str_repeat('a', 201));

        $this->assertArrayHasKey('text', $result['errors']);
        $this->assertNull($this->fresh($order)->short_note);
    }

    #[Test]
    public function nieznany_komentarz_jest_odrzucany(): void
    {
        $order = $this->order();

        $this->assertArrayHasKey('field', $this->service->comment((int) $order->getKey(), 'accounting', 'x')['errors']);
    }
}
