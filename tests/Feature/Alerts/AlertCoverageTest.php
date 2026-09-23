<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Tests\TestCase;
use App\Models\AlertRule;
use App\Alerts\ConditionCatalog;
use App\Enum\AlertConditionType;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Pokrycie katalogu warunków — strażnik przed regułą, która wygląda
 * na aktywną i nie liczy niczego.
 *
 * Reguła w bazie trzyma **nazwę** typu. Nazwa nie jest referencją: klasa
 * może zniknąć albo zmienić nazwę, a wiersz w bazie zostanie i nadal
 * będzie miał zaznaczone „aktywna". Dokładnie ta cicha awaria zdarzyła
 * się już przy uprawnieniach — `protect()` stało w kontrolerach i nie
 * chroniło niczego, bo tabela była pusta.
 */
class AlertCoverageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function kazdy_typ_z_enuma_ma_klase_w_katalogu(): void
    {
        $catalog = new ConditionCatalog();

        foreach (AlertConditionType::cases() as $case) {
            $this->assertNotNull(
                $catalog->find($case->value),
                sprintf('Typ warunku "%s" jest w enumie i nie ma klasy.', $case->value),
            );
        }
    }

    #[Test]
    public function kazda_klasa_deklaruje_ten_typ_ktory_ja_zwraca(): void
    {
        foreach ((new ConditionCatalog())->all() as $key => $condition) {
            $this->assertSame($key, $condition->type()->value);
        }
    }

    #[Test]
    public function kazda_zaseedowana_regula_wskazuje_istniejacy_typ(): void
    {
        $catalog = new ConditionCatalog();
        $rules = AlertRule::query()->get();

        // Regula startowa bez klasy nie rzucilaby bledu przy zasiewie —
        // wyszlaby dopiero na pierwszym wejsciu na liste zlecen.
        $this->assertNotSame(0, $rules->count(), 'Zasiew nie zalozyl ani jednej reguly.');

        foreach ($rules as $rule) {
            /** @var array<string, mixed> $condition */
            $condition = $rule->condition;
            $type = is_string($condition['type'] ?? null) ? $condition['type'] : '';

            $this->assertNotNull(
                $catalog->find($type),
                sprintf('Reguła "%s" wskazuje nieznany typ "%s".', $rule->code, $type),
            );
        }
    }

    #[Test]
    public function zaseedowana_regula_ma_komplet_parametrow_swojego_typu(): void
    {
        $catalog = new ConditionCatalog();

        foreach (AlertRule::query()->get() as $rule) {
            /** @var array<string, mixed> $condition */
            $condition = $rule->condition;
            $type = is_string($condition['type'] ?? null) ? $condition['type'] : '';
            $definition = $catalog->find($type);

            if ($definition === null) {
                continue;
            }

            foreach ($definition->parameters() as $parameter) {
                // Brakujacy parametr nie wywala reguly — warunek siega po
                // wartosc zapasowa. Jest wiec niewidoczny na ekranie
                // i rozjezdza sie z tym, co administrator tam widzi.
                $this->assertArrayHasKey(
                    $parameter['key'],
                    $condition,
                    sprintf('Reguła "%s" nie ma parametru "%s".', $rule->code, $parameter['key']),
                );
            }
        }
    }

    #[Test]
    public function zapis_przycina_parametry_spoza_deklaracji_typu(): void
    {
        $catalog = new ConditionCatalog();
        $condition = $catalog->find(AlertConditionType::ORDER_OVERDUE->value);

        $this->assertNotNull($condition);

        $normalized = $catalog->normalize($condition, ['days' => 3, 'wymyslony' => 'cokolwiek']);

        // Parametr spoza deklaracji osiadlby w bazie i wygladal na
        // ustawienie, ktorego nikt nie czyta.
        $this->assertSame(['type' => 'order_overdue', 'days' => 3], $normalized);
    }
}
