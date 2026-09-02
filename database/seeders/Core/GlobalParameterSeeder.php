<?php

declare(strict_types=1);

namespace Database\Seeders\Core;

use Carbon\Carbon;
use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use Salvon\Database\Seeder;
use App\Models\GlobalParameter;
use App\Enum\GlobalParameterType;

/**
 * Parametry wzoru wyceny i teksty ofertowe.
 *
 * Wszystkie wartości pochodzą z zakładki „Ogólne” starego systemu.
 * Trzy rzeczy zmienione świadomie:
 *
 * 1. `25x4` i `2250x3210` były parami liczb w polach tekstowych.
 *    Rozbite na osobne, typowane parametry.
 * 2. Etykieta mówiła „Standardowa wielkość formatki”, a opis
 *    „Maksymalna” — 3210×2250 to wymiar standardowej tafli, czyli
 *    maksimum. Nazwa idzie za znaczeniem.
 * 3. Ważność oferty istniała w dwóch miejscach: pole liczbowe mówiło
 *    10 dni, a tekst drukowany na ofercie 7. Klient dostawał jedną
 *    informację, system pilnował innej. Teraz tekst jest szablonem
 *    z podstawianą zmienną, więc nie da się ich rozjechać.
 * 4. Doszły dwa parametry, których stary ekran nie miał: sposób
 *    łączenia dopłat i moment sprawdzenia progu minimalnej ceny.
 *    Dokumentacja ich nie rozstrzyga (S-03, S-04), a różnica sięga
 *    kilkuset złotych na pozycji. Wartości domyślne odpowiadają
 *    zachowaniu odczytanemu z opisu wzoru; gdy odtworzymy je z danych
 *    starego systemu, poprawka będzie zmianą wiersza, nie kodu.
 */
class GlobalParameterSeeder extends Seeder
{
    public function run(): void
    {
        $parameters = [
            ['min_billable_m2_tempered', GlobalParameterType::NUMBER, '0.4', 'Minimalna wartość obliczeniowa formatki hartowanej (m²)'],
            ['min_billable_m2_untempered', GlobalParameterType::NUMBER, '0.1', 'Minimalna wartość obliczeniowa formatki niehartowanej (m²)'],
            ['oversize_threshold_m2', GlobalParameterType::NUMBER, '4', 'Próg gabarytu — powierzchnia formatki, powyżej której doliczana jest dopłata'],
            ['oversize_surcharge_percent', GlobalParameterType::PERCENT, '25', 'Dopłata za przekroczenie gabarytu'],
            ['shape_surcharge_percent', GlobalParameterType::PERCENT, '35', 'Dopłata za nieregularny kształt'],
            ['min_pane_price', GlobalParameterType::NUMBER, '60', 'Próg, poniżej którego formatka dostaje dopłatę'],
            ['min_pane_surcharge_percent', GlobalParameterType::PERCENT, '50', 'Dopłata dla formatki tańszej niż próg'],
            ['surcharge_mode', GlobalParameterType::CHOICE, SurchargeMode::CUMULATIVE->value, 'Sposób łączenia dopłat za kształt i gabaryt — kumulacja albo tylko najwyższa'],
            ['min_price_check', GlobalParameterType::CHOICE, MinPriceCheck::AFTER_SURCHARGES->value, 'Moment sprawdzenia progu minimalnej ceny formatki — przed dopłatami czy po nich'],
            ['max_pane_width_mm', GlobalParameterType::NUMBER, '3210', 'Maksymalna szerokość formatki — wymiar standardowej tafli'],
            ['max_pane_height_mm', GlobalParameterType::NUMBER, '2250', 'Maksymalna wysokość formatki — wymiar standardowej tafli'],
            ['offer_validity_days', GlobalParameterType::NUMBER, '10', 'Ważność oferty w dniach'],
            ['assembly_duration_days', GlobalParameterType::NUMBER, '7', 'Czas montażu w dniach'],
            ['bank_account_iban', GlobalParameterType::IBAN, null, 'Rachunek do przedpłat — do uzupełnienia, nie przenoszę z pola tekstowego starego systemu'],
            ['offer_payment_terms', GlobalParameterType::TEMPLATE, 'Warunki płatności: przedpłata na rachunek {{bank_account_iban}}', 'Tekst na ofercie'],
            ['offer_delivery_time', GlobalParameterType::TEMPLATE, 'Termin realizacji: do 30 dni roboczych', 'Tekst na ofercie'],
            ['offer_validity_text', GlobalParameterType::TEMPLATE, 'Ważność oferty: {{offer_validity_days}} dni', 'Tekst na ofercie — liczba podstawiana, żeby nie rozjechała się z parametrem'],
        ];

        foreach ($parameters as [$key, $type, $value, $description]) {
            GlobalParameter::query()->firstOrCreate(
                ['key' => $key, 'valid_from' => Carbon::today()->startOfYear()],
                [
                    'key' => $key,
                    'type' => $type->value,
                    'value' => $value,
                    'description' => $description,
                    'valid_from' => Carbon::today()->startOfYear(),
                ],
            );
        }
    }
}
