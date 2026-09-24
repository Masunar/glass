<?php

declare(strict_types=1);

namespace Tests\Unit\Orders;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Services\Orders\OrderProgress;

/**
 * Procent wpłat: sto znaczy „zapłacone", a nie „prawie".
 */
class OrderProgressTest extends TestCase
{
    #[Test]
    public function brak_grosza_to_nie_komplet(): void
    {
        $this->assertSame(99, OrderProgress::paidPercent(1229.99, 1230.0));
    }

    #[Test]
    public function pelna_wplata_to_sto(): void
    {
        // Suma groszy w liczbach zmiennoprzecinkowych bywa o wlos mniejsza.
        $this->assertSame(100, OrderProgress::paidPercent(0.1 + 0.2, 0.3));
        $this->assertSame(100, OrderProgress::paidPercent(1230.0, 1230.0));
    }

    #[Test]
    public function nadplata_nie_jest_obcinana(): void
    {
        // Nadplate trzeba zobaczyc — to pieniadze do zwrotu albo pomylka.
        $this->assertSame(110, OrderProgress::paidPercent(1353.0, 1230.0));
    }

    #[Test]
    public function bez_brutto_nie_ma_procentu(): void
    {
        $this->assertNull(OrderProgress::paidPercent(500.0, null));
        $this->assertNull(OrderProgress::paidPercent(500.0, 0.0));
    }
}
