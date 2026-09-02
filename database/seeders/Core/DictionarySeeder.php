<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use App\Models\Vehicle;
use App\Models\Location;
use App\Models\InvoiceType;
use App\Models\CashRegister;
use App\Enum\PaymentChannel;
use Salvon\Database\Seeder;

/**
 * Typy faktur, kasy i flota.
 *
 * Świadomie pominięty typ faktury „uzgodnione netto 50/50" ze stawką
 * VAT 12%. Taka stawka nie występuje w polskim systemie VAT i wygląda
 * na obejście wpisane ręcznie. To pytanie podatkowe, nie techniczne —
 * do słownika trafi dopiero po rozstrzygnięciu, czym miało być.
 */
class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        $this->invoiceTypes();
        $this->cashRegisters();
        $this->vehicles();
    }

    private function invoiceTypes(): void
    {
        $types = [
            ['VAT 23%', 23, true],
            ['VAT 0% UE', 0, false],
            ['VAT 23% UE', 23, false],
            ['Odwrotne obciążenie', 0, false],
            ['Fiskalna', 23, false],
            ['Faktura 8%', 8, false],
            ['Netto 0%', 0, false],
        ];

        $position = 0;

        foreach ($types as [$name, $vat, $isDefault]) {
            InvoiceType::query()->firstOrCreate(['name' => $name], [
                'name' => $name,
                'vat_rate' => $vat,
                'is_default' => $isDefault,
                'position' => $position += 10,
            ]);
        }
    }

    private function cashRegisters(): void
    {
        $stobno = Location::query()->where('name', 'Stobno')->first();
        $chopina = Location::query()->where('name', 'Chopina')->first();

        $registers = [
            ['Kasa Stobno', PaymentChannel::CASH, 'PLN', $stobno?->id],
            ['Kasa Chopina', PaymentChannel::CASH, 'PLN', $chopina?->id],
            ['Przelew PLN firmowy', PaymentChannel::TRANSFER, 'PLN', null],
            ['Przelew EUR firmowy', PaymentChannel::TRANSFER, 'EUR', null],
            ['Przelew USD firmowy', PaymentChannel::TRANSFER, 'USD', null],
        ];

        $position = 0;

        foreach ($registers as [$name, $channel, $currency, $locationId]) {
            CashRegister::query()->firstOrCreate(['name' => $name], [
                'name' => $name,
                'channel' => $channel->value,
                'default_currency' => $currency,
                'location_id' => $locationId,
                'position' => $position += 10,
            ]);
        }
    }

    private function vehicles(): void
    {
        // DMZ w kilogramach, wprost ze slownika starego systemu
        $vehicles = [
            ['Hyundai', 'HYI', 1100],
            ['Mercedes', 'Merc', 850],
            ['Fiat', 'Fiat', 1200],
            ['Ford', 'FORD', 700],
        ];

        $position = 0;

        foreach ($vehicles as [$name, $short, $payload]) {
            Vehicle::query()->firstOrCreate(['name' => $name], [
                'name' => $name,
                'short_name' => $short,
                'payload_kg' => $payload,
                'position' => $position += 10,
            ]);
        }
    }
}
