<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Enum\Section;
use App\Enum\AddressKind;
use App\Models\AuditEntry;
use App\Models\Contractor;
use Illuminate\Support\Str;
use App\Enum\ContractorType;
use App\Models\PriceSection;
use App\Models\ContractorAddress;
use Illuminate\Support\Facades\Auth;
use App\Models\ContractorPriceSection;
use Illuminate\Database\Eloquent\Builder;
use Salvon\Regon\Validator\Nip;
use Salvon\Regon\Validator\Regon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Kartoteka kontrahentów.
 *
 * Kontrahent niesie trzy rzeczy decydujące o przebiegu zlecenia: jakie
 * ceny widzi, na jakich warunkach kupuje i ile już jest winien.
 * Pierwsze dwie są tutaj; trzecia dojdzie z modułem wpłat.
 */
final readonly class ContractorService
{
    /**
     * @return array<string, mixed>
     */
    public function list(?string $query = null, bool $includeInactive = false, int $limit = 100): array
    {
        $search = $query !== null ? trim($query) : '';

        /** @var Collection<int, Contractor> $rows */
        $rows = Contractor::query()
            ->with(['priceSections.priceSection', 'primaryContact'])
            ->when(!$includeInactive, static fn(Builder $q): Builder => $q->where('is_active', true))
            // Szukanie od trzech znakow: krotsze zapytanie zwraca pol
            // kartoteki i nic nie wnosi.
            ->when(mb_strlen($search) >= 3, static function (Builder $q) use ($search): Builder {
                $like = '%' . $search . '%';

                return $q->where(static function (Builder $inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('short_name', 'like', $like)
                        ->orWhere('tax_id', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return [
            'contractors' => $rows->map(fn(Contractor $row): array => $this->row($row))->values()->all(),
            'total' => Contractor::query()->where('is_active', true)->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function card(int $contractorId): array
    {
        /** @var Contractor $contractor */
        $contractor = Contractor::query()
            ->with(['addresses', 'contacts', 'priceSections.priceSection'])
            ->findOrFail($contractorId);

        return [
            'contractor' => $this->row($contractor) + [
                'registry_id' => $contractor->registry_id,
                'first_name' => $contractor->first_name,
                'last_name' => $contractor->last_name,
                'website' => $contractor->website,
                'note' => $contractor->note,
                'is_supplier' => $contractor->is_supplier,
                'registered_on' => $contractor->registered_on?->toDateString(),
            ],
            'addresses' => $this->addresses($contractor),
            'contacts' => $contractor->contacts
                ->map(static fn($contact): array => [
                    'id' => $contact->id,
                    'first_name' => $contact->first_name,
                    'last_name' => $contact->last_name,
                    'position' => $contact->position,
                    'phone' => $contact->phone,
                    'email' => $contact->email,
                    'is_primary' => (bool) $contact->is_primary,
                ])
                ->values()
                ->all(),
            'price_sections' => $this->priceSections($contractor),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{errors: array<string, list<string>>, id: int|null}
     */
    public function save(array $input, ?int $contractorId = null): array
    {
        $errors = $this->validate($input, $contractorId);

        if ($errors !== []) {
            return ['errors' => $errors, 'id' => null];
        }

        $contractor = $contractorId === null
            ? new Contractor()
            : Contractor::query()->findOrFail($contractorId);

        $before = $contractorId === null
            ? null
            : $contractor->only(['name', 'tax_id', 'payment_days', 'credit_limit', 'is_active']);

        $contractor->fill([
            'type' => (string) $input['type'],
            'name' => trim((string) $input['name']),
            'short_name' => $this->nullable($input['short_name'] ?? null),
            'tax_id' => $this->digits($input['tax_id'] ?? null),
            'registry_id' => $this->digits($input['registry_id'] ?? null),
            'first_name' => $this->nullable($input['first_name'] ?? null),
            'last_name' => $this->nullable($input['last_name'] ?? null),
            'phone' => $this->nullable($input['phone'] ?? null),
            'email' => $this->nullable($input['email'] ?? null),
            'website' => $this->nullable($input['website'] ?? null),
            'payment_days' => (int) ($input['payment_days'] ?? 0),
            'credit_limit' => number_format((float) ($input['credit_limit'] ?? 0), 2, '.', ''),
            'is_supplier' => (bool) ($input['is_supplier'] ?? false),
            'is_active' => (bool) ($input['is_active'] ?? true),
            'note' => $this->nullable($input['note'] ?? null),
        ]);

        if ($contractorId === null) {
            $contractor->registered_on = Carbon::today();
            $contractor->created_by = Auth::id();
        }

        $contractor->save();

        $this->saveAddress($contractor, AddressKind::REGISTERED, $input['address'] ?? null);

        $this->audit(
            (int) $contractor->id,
            $before,
            $contractor->only(['name', 'tax_id', 'payment_days', 'credit_limit', 'is_active']),
        );

        return ['errors' => [], 'id' => (int) $contractor->id];
    }

    /**
     * Przypisanie sekcji cenowych — poziom 2 ustalania ceny.
     *
     * Pusta wartość usuwa przypisanie, a wtedy obowiązuje sekcja
     * domyślna. To świadomy wybór: brak przypisania nie może blokować
     * wyceny nowego klienta.
     *
     * @param array<string, mixed> $sections sekcja asortymentu => id sekcji cenowej
     * @return array<string, list<string>>
     */
    public function savePriceSections(int $contractorId, array $sections): array
    {
        /** @var Contractor $contractor */
        $contractor = Contractor::query()->findOrFail($contractorId);

        $errors = [];

        foreach ($sections as $key => $value) {
            $section = Section::tryFrom((string) $key);

            if ($section === null) {
                $errors[(string) $key] = ['Nie ma takiej sekcji asortymentu.'];
                continue;
            }

            if ($value === null || $value === '') {
                $contractor->priceSections()->where('section', $section->value)->delete();
                continue;
            }

            /** @var PriceSection|null $priceSection */
            $priceSection = PriceSection::query()->find((int) $value);

            if ($priceSection === null || $priceSection->section !== $section) {
                $errors[(string) $key] = ['Ta sekcja cenowa nie należy do tej sekcji asortymentu.'];
                continue;
            }

            ContractorPriceSection::query()->updateOrCreate(
                ['contractor_id' => $contractor->id, 'section' => $section->value],
                [
                    'contractor_id' => $contractor->id,
                    'section' => $section->value,
                    'price_section_id' => $priceSection->id,
                    'changed_by' => Auth::id(),
                ],
            );
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    private function row(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'type' => $contractor->type->value,
            'name' => $contractor->name,
            'short_name' => $contractor->short_name,
            'display_name' => $contractor->displayName(),
            'tax_id' => $contractor->tax_id,
            'phone' => $contractor->phone,
            'email' => $contractor->email,
            'payment_days' => $contractor->payment_days,
            'credit_limit' => $contractor->credit_limit,
            'is_active' => (bool) $contractor->is_active,
        ];
    }

    /** @return array<string, mixed> */
    private function addresses(Contractor $contractor): array
    {
        $result = [];

        foreach (AddressKind::cases() as $kind) {
            $address = $contractor->addresses->firstWhere('kind', $kind);

            $result[$kind->value] = $address === null ? null : [
                'country' => $address->country,
                'voivodeship' => $address->voivodeship,
                'county' => $address->county,
                'post_office' => $address->post_office,
                'city' => $address->city,
                'postal_code' => $address->postal_code,
                'street' => $address->street,
                'building_number' => $address->building_number,
                'unit_number' => $address->unit_number,
                'one_line' => $address->oneLine(),
            ];
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private function priceSections(Contractor $contractor): array
    {
        $assigned = $contractor->priceSections->keyBy(
            static fn(ContractorPriceSection $item): string => $item->section->value,
        );

        $result = [];

        foreach (Section::cases() as $section) {
            /** @var ContractorPriceSection|null $item */
            $item = $assigned->get($section->value);

            /** @var PriceSection|null $fallback */
            $fallback = PriceSection::query()
                ->where('section', $section->value)
                ->where('is_default', true)
                ->first();

            $result[] = [
                'section' => $section->value,
                'price_section_id' => $item?->price_section_id,
                'price_section_name' => $item?->priceSection?->name,
                'default_name' => $fallback?->name,
            ];
        }

        return $result;
    }

    private function saveAddress(Contractor $contractor, AddressKind $kind, mixed $input): void
    {
        if (!is_array($input)) {
            return;
        }

        ContractorAddress::query()->updateOrCreate(
            ['contractor_id' => $contractor->id, 'kind' => $kind->value],
            [
                'contractor_id' => $contractor->id,
                'kind' => $kind->value,
                'country' => $this->nullable($input['country'] ?? null) ?? 'PL',
                'voivodeship' => $this->nullable($input['voivodeship'] ?? null),
                'county' => $this->nullable($input['county'] ?? null),
                'post_office' => $this->nullable($input['post_office'] ?? null),
                'city' => $this->nullable($input['city'] ?? null),
                'postal_code' => $this->nullable($input['postal_code'] ?? null),
                'street' => $this->nullable($input['street'] ?? null),
                'building_number' => $this->nullable($input['building_number'] ?? null),
                'unit_number' => $this->nullable($input['unit_number'] ?? null),
            ],
        );
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, list<string>>
     */
    private function validate(array $input, ?int $contractorId): array
    {
        $type = ContractorType::tryFrom((string) ($input['type'] ?? ''));

        if ($type === null) {
            return ['type' => ['Wskaż, czy to firma, czy osoba prywatna.']];
        }

        // Numery normalizujemy przed walidacja, zeby "852-234-70-66"
        // przechodzil tak samo jak "8522347066".
        $input['tax_id'] = $this->digits($input['tax_id'] ?? null);
        $input['registry_id'] = $this->digits($input['registry_id'] ?? null);

        // Zadne pole kontaktowe nie jest wymagane, ale jesli zostalo
        // wypelnione, musi miec sens. Stara baza pelna jest telefonow "0"
        // i e-maili "123" wpisanych tylko po to, zeby formularz przepuscil.
        $rules = [
            'name' => ['required', 'string', 'max:200'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'tax_id' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'registry_id' => ['nullable', 'string', 'regex:/^[0-9]{9}([0-9]{5})?$/'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:120'],
            'website' => ['nullable', 'string', 'max:120'],
            'payment_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'credit_limit' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];

        if ($type === ContractorType::COMPANY) {
            $rules['tax_id'] = ['required', 'string', 'regex:/^[0-9]{10}$/'];
        }

        $validator = Validator::make($input, $rules);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $messages */
            $messages = $validator->errors()->messages();

            return $messages;
        }

        $taxId = $input['tax_id'];

        if ($taxId !== null && !$this->isValidTaxId($taxId)) {
            // Suma kontrolna lapie literowke, ktora inaczej zalozylaby
            // drugi rekord tej samej firmy pod blednym numerem.
            return ['tax_id' => ['NIP ma niepoprawną sumę kontrolną.']];
        }

        // Telefon liczymy w cyfrach, nie w znakach. Wymaganie dziewieciu
        // cyfr pod rzad odrzucalo "914 843 703" - czyli dokladnie ten
        // zapis, ktorym numery sa podawane i wyswietlane wszedzie indziej.
        $phoneDigits = $this->digits($input['phone'] ?? null);

        if ($phoneDigits !== null && mb_strlen($phoneDigits) < 9) {
            return ['phone' => ['Numer telefonu musi mieć co najmniej dziewięć cyfr.']];
        }

        $registryId = $input['registry_id'];

        if ($registryId !== null && !Regon::isValid($registryId)) {
            // REGON ma własną sumę kontrolną — 9 cyfr i osobną dla 14.
            return ['registry_id' => ['REGON ma niepoprawną sumę kontrolną.']];
        }

        if ($taxId !== null) {
            $taken = Contractor::query()
                ->where('tax_id', $taxId)
                ->when($contractorId !== null, static fn(Builder $q): Builder => $q->whereKeyNot($contractorId))
                ->exists();

            if ($taken) {
                // Duplikaty kontrahentow sa realnym problemem starej bazy:
                // dwa rekordy tej samej firmy rozbijaja historie klienta.
                return ['tax_id' => ['Kontrahent z tym NIP-em już istnieje.']];
            }
        }

        return [];
    }

    /**
     * @param array<string, mixed>|null $before
     * @param array<string, mixed> $after
     */
    private function audit(int $id, ?array $before, array $after): void
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $previous = $before[$field] ?? null;

            if ($before !== null && $previous === $value) {
                continue;
            }

            $changes[] = ['field' => $field, 'before' => $previous, 'after' => $value];
        }

        if ($changes === []) {
            return;
        }

        AuditEntry::query()->create([
            'edit_session_id' => (string) Str::uuid(),
            'auditable_type' => Contractor::class,
            'auditable_id' => $id,
            'user_id' => Auth::id(),
            'event' => $before === null ? 'created' : 'updated',
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * Suma kontrolna NIP-u pochodzi z Salvona — ten sam algorytm siedzi
     * już w pakiecie GUS/REGON, którego używa podpowiadanie danych firmy.
     * Zostaje tu jedyna rzecz, której Salvon nie pilnuje: numery-wypełniacze
     * w rodzaju 1111111111, którymi stara baza jest usiana.
     */
    private function isValidTaxId(string $taxId): bool
    {
        if (preg_match('/^(\d)\1{9}$/', $taxId) === 1) {
            return false;
        }

        return Nip::isValid($taxId);
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return trim((string) $value);
    }

    /** NIP i REGON trzymamy jako same cyfry — myślniki są ozdobą wpisu. */
    private function digits(mixed $value): ?string
    {
        $text = $this->nullable($value);

        if ($text === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $text) ?? '';

        return $digits === '' ? null : $digits;
    }
}
