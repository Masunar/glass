<?php

declare(strict_types=1);

namespace Database\Seeders\Dev;

use Carbon\Carbon;
use App\Enum\Section;
use App\Models\Product;
use App\Enum\AddressKind;
use App\Models\Contractor;
use Salvon\Database\Seeder;
use App\Models\PriceSection;
use App\Models\ContractorPrice;
use App\Models\ContractorAddress;
use App\Models\ContractorContact;
use App\Models\ContractorPriceSection;
use Illuminate\Database\Eloquent\Collection;

/**
 * Dwadzieścia kartotek kontrahentów do pracy nad ekranami.
 *
 * Dane są zmyślone, ale nie przypadkowe. NIP-y i REGON-y mają poprawne
 * sumy kontrolne, bo walidacja zapisu je sprawdza — kartoteka z numerem
 * z palca nie dałaby się otworzyć i zapisać z powrotem. Domeny pocztowe
 * kończą się na .example, żeby żaden test nie wysłał maila pod adres,
 * który do kogoś należy.
 *
 * Rozkład celowo obejmuje przypadki brzegowe istniejące w kartotece:
 * osoba prywatna bez NIP-u, kontrahent wyłączony, firma będąca
 * jednocześnie dostawcą, klient na przedpłacie, klient z wysokim
 * limitem i dwie firmy o myląco podobnych nazwach.
 */
class ContractorSeeder extends Seeder
{
    public function run(): void
    {
        // Seeder deweloperski nie dopisuje sie do istniejacej kartoteki:
        // ponowne odpalenie na wypelnionej bazie zrobiloby duplikaty,
        // ktorych ten system ma wlasnie unikac.
        if (Contractor::query()->exists()) {
            return;
        }

        $sections = PriceSection::query()->get();
        $today = Carbon::today();

        foreach ($this->rows() as $index => $row) {
            /** @var Contractor $contractor */
            $contractor = Contractor::query()->create([
                'type' => $row['type'],
                'name' => $row['name'],
                'short_name' => $row['short_name'],
                'tax_id' => $row['tax_id'],
                'registry_id' => $row['registry_id'],
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'phone' => $row['phone'],
                'email' => $row['email'],
                'payment_days' => $row['payment_days'],
                'credit_limit' => number_format((float) $row['credit_limit'], 2, '.', ''),
                'is_supplier' => $row['is_supplier'],
                'is_active' => $row['is_active'],
                'note' => $row['note'],
                // Rozlozone w czasie, zeby lista sortowana po dacie
                // zalozenia nie byla jednym slupkiem.
                'registered_on' => $today->copy()->subDays(($index + 1) * 23),
            ]);

            ContractorAddress::query()->create([
                'contractor_id' => $contractor->id,
                'kind' => AddressKind::REGISTERED->value,
                'country' => 'PL',
                'city' => $row['city'],
                'postal_code' => $row['postal_code'],
                'street' => $row['street'],
                'building_number' => $row['building'],
            ]);

            if ($row['contact'] !== null) {
                [$first, $last, $position, $phone] = $row['contact'];

                ContractorContact::query()->create([
                    'contractor_id' => $contractor->id,
                    'first_name' => $first,
                    'last_name' => $last,
                    'position' => $position,
                    'phone' => $phone,
                    'is_primary' => true,
                ]);
            }

            $this->assignPriceSections($contractor, $sections, $row['price_level']);
            $this->assignIndividualPrice($contractor, $row['individual_glass_price'], $today);
        }
    }

    /**
     * Poziom cenowy per sekcja asortymentu. Brak przypisania oznacza
     * sekcje domyslna, wiec czesc kontrahentow zostaje bez wpisu — tak
     * jak w rzeczywistej kartotece.
     *
     * @param Collection<int, PriceSection> $sections
     */
    private function assignPriceSections(
        Contractor $contractor,
        Collection $sections,
        ?int $level,
    ): void {
        if ($level === null) {
            return;
        }

        foreach (Section::cases() as $section) {
            $available = $sections
                ->where('section', $section)
                ->sortBy('position')
                ->values();

            if ($available->isEmpty()) {
                continue;
            }

            /** @var PriceSection $chosen */
            $chosen = $available->get(min($level, $available->count() - 1));

            ContractorPriceSection::query()->create([
                'contractor_id' => $contractor->id,
                'section' => $section->value,
                'price_section_id' => $chosen->id,
            ]);
        }
    }

    /**
     * Cena indywidualna to trzeci poziom wyceny — nadpisuje cennik
     * sekcji. Kilka takich wpisow jest potrzebnych, zeby slad wyceny
     * na zleceniu mial co pokazac.
     */
    private function assignIndividualPrice(
        Contractor $contractor,
        ?string $netPrice,
        Carbon $today,
    ): void {
        if ($netPrice === null) {
            return;
        }

        $product = Product::query()
            ->where('section', Section::GLASS->value)
            ->orderBy('id')
            ->first();

        if ($product === null) {
            return;
        }

        ContractorPrice::query()->create([
            'contractor_id' => $contractor->id,
            'product_id' => $product->id,
            'net_price' => $netPrice,
            'valid_from' => $today->copy()->subMonths(2),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rows(): array
    {
        return [
            [
                'type' => 'company', 'name' => 'STECKO MEBLE sp. z o.o. sp.k.', 'short_name' => 'Stecko Meble',
                'tax_id' => '6532694845', 'registry_id' => '603490888',
                'phone' => '914 843 703', 'email' => 'biuro@steckomeble.example',
                'city' => 'Szczecin', 'postal_code' => '70-344', 'street' => 'Chodkiewicza', 'building' => '2',
                'payment_days' => 14, 'credit_limit' => 2500, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Faktury zbiorcze na koniec miesiąca.',
                'contact' => ['Marta', 'Stecko', 'zaopatrzenie', '602 118 004'],
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'ALU-SYSTEM sp. z o.o.', 'short_name' => 'Alu-System',
                'tax_id' => '6903072257', 'registry_id' => '362085987',
                'phone' => '914 002 118', 'email' => 'zamowienia@alusystem.example',
                'city' => 'Police', 'postal_code' => '72-010', 'street' => 'Przemysłowa', 'building' => '14',
                'payment_days' => 30, 'credit_limit' => 18000, 'is_supplier' => false, 'is_active' => true,
                'note' => null,
                'contact' => ['Tomasz', 'Wilk', 'kierownik projektów', '601 774 302'],
                'price_level' => 1, 'individual_glass_price' => '58.00',
            ],
            [
                'type' => 'company', 'name' => 'PW „EBUD" — Przemysłówka sp. z o.o.', 'short_name' => 'EBUD',
                'tax_id' => '2499019066', 'registry_id' => '271980356',
                'phone' => '784 978 723', 'email' => 'sekretariat@ebud.example',
                'city' => 'Bydgoszcz', 'postal_code' => '85-758', 'street' => 'Fordońska', 'building' => '246',
                'payment_days' => 30, 'credit_limit' => 25000, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Wymagają protokołu odbioru przy każdej dostawie.',
                'contact' => ['Anna', 'Ostrowska', 'kosztorys', '512 004 771'],
                'price_level' => 1, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'METAL MADNESS Paweł Hołownia', 'short_name' => 'Metal Madness',
                'tax_id' => '4315728010', 'registry_id' => '247170417',
                'phone' => '601 774 302', 'email' => 'pawel@metalmadness.example',
                'city' => 'Szczecin', 'postal_code' => '71-062', 'street' => 'Ku Słońcu', 'building' => '118',
                'payment_days' => 21, 'credit_limit' => 6000, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Otwarta reklamacja: hartowanie 8 mm, partia z lipca.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Vitrofloat Dystrybucja sp. z o.o.', 'short_name' => 'Vitrofloat',
                'tax_id' => '9926046213', 'registry_id' => '461857580',
                'phone' => '914 500 100', 'email' => 'zamowienia@vitrofloat.example',
                'city' => 'Sandomierz', 'postal_code' => '27-600', 'street' => 'Portowa', 'building' => '24',
                'payment_days' => 45, 'credit_limit' => 0, 'is_supplier' => true, 'is_active' => true,
                'note' => 'Dostawca float i szkła niskoemisyjnego.',
                'contact' => ['Robert', 'Nowak', 'opiekun handlowy', '668 120 044'],
                'price_level' => null, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Okucia Wschód sp. z o.o.', 'short_name' => 'Okucia Wschód',
                'tax_id' => '1607630121', 'registry_id' => '211565279',
                'phone' => '857 220 118', 'email' => 'handel@okuciawschod.example',
                'city' => 'Białystok', 'postal_code' => '15-399', 'street' => 'Składowa', 'building' => '7',
                'payment_days' => 30, 'credit_limit' => 0, 'is_supplier' => true, 'is_active' => true,
                'note' => 'Dostawca zawiasów i klamek do drzwi szklanych.',
                'contact' => null,
                'price_level' => null, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'PPHU Wnętrza Jan Nowicki', 'short_name' => 'Wnętrza Nowicki',
                'tax_id' => '7522101264', 'registry_id' => '242110540',
                'phone' => '693 118 442', 'email' => 'biuro@wnetrza-nowicki.example',
                'city' => 'Stargard', 'postal_code' => '73-110', 'street' => 'Kościuszki', 'building' => '41',
                'payment_days' => 14, 'credit_limit' => 4000, 'is_supplier' => false, 'is_active' => true,
                'note' => null, 'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Dental Art Barbara Stawska', 'short_name' => 'Dental Art',
                'tax_id' => '1986913365', 'registry_id' => '550306109',
                'phone' => '606 769 903', 'email' => 'recepcja@dentalart.example',
                'city' => 'Szczecin', 'postal_code' => '70-111', 'street' => 'Powstańców Wielkopolskich', 'building' => '72',
                'payment_days' => 0, 'credit_limit' => 0, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Przedpłata — jednorazowe zlecenia gabinetowe.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Uczniowski Klub Sportowy Sport Dance', 'short_name' => 'UKS Sport Dance',
                'tax_id' => '4659647135', 'registry_id' => '248452780',
                'phone' => '518 640 187', 'email' => 'kontakt@sportdance.example',
                'city' => 'Szczecin', 'postal_code' => '71-004', 'street' => 'Witkiewicza', 'building' => '10',
                'payment_days' => 0, 'credit_limit' => 0, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Lustra do sali — zamówienia raz w roku.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Stecko Bud Marcin Stecko', 'short_name' => 'Stecko Bud',
                'tax_id' => '8230327312', 'registry_id' => '674984922',
                'phone' => '602 118 004', 'email' => 'm.stecko@steckobud.example',
                'city' => 'Police', 'postal_code' => '72-010', 'street' => 'Wojska Polskiego', 'building' => '3',
                'payment_days' => 0, 'credit_limit' => 0, 'is_supplier' => false, 'is_active' => true,
                // Podobna nazwa i to samo nazwisko co w pierwszej pozycji —
                // dokladnie ten przypadek, na ktory reaguje ostrzezenie
                // o duplikacie w panelu nowego kontrahenta.
                'note' => 'Osobna firma niż STECKO MEBLE — wspólny właściciel.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'GlassTech Instalacje sp. z o.o.', 'short_name' => 'GlassTech',
                'tax_id' => '3899275135', 'registry_id' => '304803548',
                'phone' => '914 771 220', 'email' => 'biuro@glasstech.example',
                'city' => 'Goleniów', 'postal_code' => '72-100', 'street' => 'Lipowa', 'building' => '18',
                'payment_days' => 21, 'credit_limit' => 9000, 'is_supplier' => false, 'is_active' => true,
                'note' => null,
                'contact' => ['Karolina', 'Wrona', 'zamówienia', '512 900 118'],
                'price_level' => 1, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Balustrady Pomorze Adam Wilk', 'short_name' => 'Balustrady Pomorze',
                'tax_id' => '6179387652', 'registry_id' => '444441140',
                'phone' => '505 200 118', 'email' => 'adam@balustradypomorze.example',
                'city' => 'Koszalin', 'postal_code' => '75-032', 'street' => 'Zwycięstwa', 'building' => '140',
                'payment_days' => 14, 'credit_limit' => 7500, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Zamawia komplety: szkło + okucia + montaż.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => '61.50',
            ],
            [
                'type' => 'company', 'name' => 'Hotel Nadmorski sp. z o.o.', 'short_name' => 'Hotel Nadmorski',
                'tax_id' => '4502922884', 'registry_id' => '942688056',
                'phone' => '943 442 010', 'email' => 'technik@hotelnadmorski.example',
                'city' => 'Kołobrzeg', 'postal_code' => '78-100', 'street' => 'Morska', 'building' => '4',
                'payment_days' => 30, 'credit_limit' => 12000, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Kabiny prysznicowe — wymiana partiami po sezonie.',
                'contact' => ['Grzegorz', 'Lis', 'kierownik techniczny', '664 220 901'],
                'price_level' => 1, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Stolarnia Bracia Mazur s.c.', 'short_name' => 'Bracia Mazur',
                'tax_id' => '2610895367', 'registry_id' => '356976308',
                'phone' => '601 220 118', 'email' => 'stolarnia@braciamazur.example',
                'city' => 'Gryfino', 'postal_code' => '74-100', 'street' => 'Łużycka', 'building' => '26',
                'payment_days' => 14, 'credit_limit' => 3500, 'is_supplier' => false, 'is_active' => true,
                'note' => null, 'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Nowa Przystań Deweloper sp. z o.o.', 'short_name' => 'Nowa Przystań',
                'tax_id' => '4424693144', 'registry_id' => '304950278',
                'phone' => '914 118 220', 'email' => 'inwestycje@nowaprzystan.example',
                'city' => 'Szczecin', 'postal_code' => '70-800', 'street' => 'Struga', 'building' => '61',
                'payment_days' => 45, 'credit_limit' => 40000, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Limit powyżej reszty kartoteki — zlecenia etapowe.',
                'contact' => ['Piotr', 'Zawada', 'kierownik budowy', '693 004 771'],
                'price_level' => 2, 'individual_glass_price' => null,
            ],
            [
                'type' => 'person', 'name' => 'Katarzyna Malinowska', 'short_name' => null,
                'tax_id' => null, 'registry_id' => null,
                'first_name' => 'Katarzyna', 'last_name' => 'Malinowska',
                'phone' => '668 120 044', 'email' => 'k.malinowska@example.com',
                'city' => 'Szczecin', 'postal_code' => '71-220', 'street' => 'Sosnowa', 'building' => '12',
                'payment_days' => 0, 'credit_limit' => 0, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Osoba prywatna — lustro do łazienki.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'person', 'name' => 'Marek Jaworski', 'short_name' => null,
                'tax_id' => null, 'registry_id' => null,
                'first_name' => 'Marek', 'last_name' => 'Jaworski',
                'phone' => null, 'email' => null,
                'city' => 'Police', 'postal_code' => '72-010', 'street' => 'Kwiatowa', 'building' => '5',
                'payment_days' => 0, 'credit_limit' => 0, 'is_supplier' => false, 'is_active' => true,
                // Kartoteka bez telefonu i e-maila: pola nie sa wymagane,
                // bo w starej bazie wpisywano do nich "0" i "123".
                'note' => 'Kontakt wyłącznie osobisty w punkcie.',
                'contact' => null,
                'price_level' => null, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Vitrum Serwis Tomasz Leśny', 'short_name' => 'Vitrum Serwis',
                'tax_id' => '9964673188', 'registry_id' => '618987901',
                'phone' => '512 004 771', 'email' => 'serwis@vitrum.example',
                'city' => 'Świnoujście', 'postal_code' => '72-600', 'street' => 'Grunwaldzka', 'building' => '33',
                'payment_days' => 14, 'credit_limit' => 2000, 'is_supplier' => false, 'is_active' => false,
                'note' => 'Współpraca zakończona — kartoteka zostaje dla historii zleceń.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Aranżacje Wnętrz Studio S sp. z o.o.', 'short_name' => 'Studio S',
                'tax_id' => '7973727416', 'registry_id' => '167181012',
                'phone' => '664 220 901', 'email' => 'studio@aranzacje-s.example',
                'city' => 'Szczecin', 'postal_code' => '70-483', 'street' => 'Wojska Polskiego', 'building' => '90',
                'payment_days' => 21, 'credit_limit' => 5000, 'is_supplier' => false, 'is_active' => true,
                'note' => 'Projektant — zamówienia w imieniu klientów końcowych.',
                'contact' => ['Ewa', 'Sikora', 'projektantka', '693 118 442'],
                'price_level' => 1, 'individual_glass_price' => null,
            ],
            [
                'type' => 'company', 'name' => 'Zakład Szklarski Kryształ Jan Bąk', 'short_name' => 'Kryształ',
                'tax_id' => '3779609800', 'registry_id' => '947664020',
                'phone' => '947 220 118', 'email' => 'krysztal@szklarz.example',
                'city' => 'Wałcz', 'postal_code' => '78-600', 'street' => 'Bydgoska', 'building' => '9',
                'payment_days' => 7, 'credit_limit' => 1500, 'is_supplier' => false, 'is_active' => false,
                'note' => 'Wyłączony do czasu uregulowania zaległości.',
                'contact' => null,
                'price_level' => 0, 'individual_glass_price' => null,
            ],
        ];
    }
}
