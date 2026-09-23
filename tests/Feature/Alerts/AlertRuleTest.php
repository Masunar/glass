<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use Tests\TestCase;
use App\Models\Role;
use App\Models\User;
use App\Models\AlertRule;
use App\Models\AlertOccurrence;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Ekran reguł alertów.
 *
 * Reguła jest daną, ale nie dowolną. Testowane jest to, czego **nie
 * da się zapisać**: typ spoza katalogu, kod w formacie, którego nikt nie
 * odczyta, parametr, którego typ nie zna. Walidacja, która puszcza
 * regułę nie do policzenia, produkuje wiersz wyglądający na aktywny.
 */
class AlertRuleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function ekran_regul_wymaga_uprawnienia_i_dostepu_do_modulu(): void
    {
        $this->actingAs($this->userWith(['alerts.list']))
            ->getJson('/api/alerts')
            ->assertForbidden();

        $this->actingAs($this->userWith(['adm.access']))
            ->getJson('/api/alerts')
            ->assertForbidden();

        $this->actingAs($this->userWith(['adm.access', 'alerts.list']))
            ->getJson('/api/alerts')
            ->assertOk();
    }

    #[Test]
    public function alert_nie_ma_wlasnego_uprawnienia_do_odczytu(): void
    {
        // Alert dotyczy zlecenia, wiec widzi go ten, kto widzi zlecenia.
        // Osobne `alerts.list` na liscie zlecen znaczyloby, ze handlowiec
        // patrzy na liste, na ktorej brakuje polowy sygnalow.
        $user = $this->userWith(['zlec.access', 'orders.list']);

        $this->actingAs($user)
            ->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('data.filters.0.alerts', 0);
    }

    #[Test]
    public function typ_spoza_katalogu_nie_przechodzi(): void
    {
        $user = $this->userWith(['adm.access', 'alerts.create']);

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'code' => 'moja_regula',
                'name' => 'Moja reguła',
                'label' => 'moje',
                'type' => 'zlecenie_wyglada_podejrzanie',
            ])
            // Salvon zwraca 400 z bledami w `data`, nie 422 z `errors`.
            ->assertStatus(400)
            ->assertJsonPath('data.type.0', 'Wybierz typ warunku z katalogu.');

        $this->assertFalse(AlertRule::query()->where('code', 'moja_regula')->exists());
    }

    #[Test]
    public function kod_reguly_musi_byc_kodem_i_musi_byc_jeden(): void
    {
        $user = $this->userWith(['adm.access', 'alerts.create']);

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'code' => 'Moja Reguła!',
                'name' => 'Moja reguła',
                'label' => 'moje',
                'type' => 'order_overdue',
            ])
            ->assertStatus(400)
            ->assertJsonStructure(['data' => ['code']]);

        $this->actingAs($user)
            ->postJson('/api/alerts', [
                'code' => 'order_overdue',
                'name' => 'Druga taka sama',
                'label' => 'moje',
                'type' => 'order_overdue',
            ])
            ->assertStatus(400)
            ->assertJsonStructure(['data' => ['code']]);
    }

    #[Test]
    public function zmiana_warunku_zamyka_wystapienia_policzone_starym(): void
    {
        $user = $this->userWith(['adm.access', 'alerts.update', 'zlec.access', 'orders.list']);

        /** @var AlertRule $rule */
        $rule = AlertRule::query()->where('code', 'order_overdue')->firstOrFail();

        AlertOccurrence::query()->create([
            'alert_rule_id' => $rule->getKey(),
            'alertable_type' => \App\Models\Order::class,
            'alertable_id' => 999_999,
            'value' => '3',
        ]);

        $this->actingAs($user)
            ->putJson('/api/alerts/' . $rule->getKey(), [
                'code' => $rule->code,
                'name' => $rule->name,
                'label' => $rule->label,
                'type' => 'order_overdue',
                'params' => ['days' => 7],
            ])
            ->assertOk();

        // Wystapienie policzone progiem jednego dnia nie jest
        // wystapieniem reguly siedmiodniowej. Zostawione, wisialoby na
        // ekranie jako alert, ktorego nic juz nie przelicza.
        $this->assertNotNull(
            AlertOccurrence::query()->where('alertable_id', 999_999)->first()?->resolved_at,
        );
    }

    /** @param list<string> $permissions */
    private function userWith(array $permissions): User
    {
        /** @var Role $role */
        $role = Role::query()->create([
            'name' => 'Rola alertów ' . random_int(1000, 9999),
            'guard_name' => 'web',
        ]);

        $role->syncPermissions($permissions);

        /** @var User $user */
        $user = User::query()->create([
            'first_name' => 'Test',
            'last_name' => 'Alerty',
            'email' => 'alerty' . random_int(1000, 9999) . '@example.test',
            'password' => 'secret-not-used',
            'is_active' => true,
        ]);

        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh() ?? $user;
    }
}
