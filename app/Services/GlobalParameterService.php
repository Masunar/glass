<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use App\Models\AuditEntry;
use Illuminate\Support\Str;
use App\Models\GlobalParameter;
use Illuminate\Validation\Rule;
use App\Enum\GlobalParameterType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Odczyt i zmiana parametrów wzoru wyceny oraz tekstów ofertowych.
 *
 * Zapis jest wersjonowany, a nie nadpisujący: zmiana dopłaty za kształt
 * z 35 na 40 zmienia ceny wszystkich nowych ofert, więc musi zostawiać
 * ślad kto, kiedy i z jakiej wartości na jaką. Stary system nie zostawiał
 * żadnego.
 */
final readonly class GlobalParameterService
{
    /** @return list<array<string, mixed>> */
    public function list(?Carbon $date = null): array
    {
        $date ??= Carbon::today();

        return $this->effective($date)
            ->map(static fn(GlobalParameter $parameter): array => [
                'key' => $parameter->key,
                'type' => $parameter->type->value,
                'value' => $parameter->value,
                'description' => $parameter->description,
                'options' => GlobalParameter::choicesFor($parameter->key),
                'valid_from' => $parameter->valid_from->toDateString(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param array<string, string|null> $input
     * @return array<string, list<string>> błędy walidacji, puste gdy zapis się powiódł
     */
    public function update(array $input): array
    {
        $today = Carbon::today();
        /** @var Collection<string, GlobalParameter> $current */
        $current = $this->effective($today)->keyBy('key');

        $errors = $this->validate($input, $current);

        if ($errors !== []) {
            return $errors;
        }

        // Jedna sesja edycji dla calego zapisu - zmiany z jednego
        // klikniecia maja byc w dzienniku jednym wpisem, a nie dwunastoma.
        $editSession = (string) Str::uuid();
        $changes = [];

        foreach ($input as $key => $value) {
            /** @var GlobalParameter|null $parameter */
            $parameter = $current->get($key);

            if ($parameter === null || $parameter->value === $value) {
                continue;
            }

            $changes[] = [
                'field' => $key,
                'before' => $parameter->value,
                'after' => $value,
            ];

            $this->writeNewVersion($parameter, $value, $today);
        }

        if ($changes !== []) {
            AuditEntry::query()->create([
                'edit_session_id' => $editSession,
                'auditable_type' => GlobalParameter::class,
                'auditable_id' => 0,
                'user_id' => Auth::id(),
                'event' => 'updated',
                'changes' => $changes,
                'ip_address' => request()->ip(),
            ]);
        }

        return [];
    }

    private function writeNewVersion(GlobalParameter $parameter, ?string $value, Carbon $today): void
    {
        // Wersja zalozona dzisiaj jest poprawiana w miejscu - inaczej
        // powstalby wiersz o zerowej dlugosci obowiazywania.
        if ($parameter->valid_from->isSameDay($today)) {
            $parameter->update(['value' => $value, 'changed_by' => Auth::id()]);

            return;
        }

        $parameter->update(['valid_to' => $today->copy()->subDay()]);

        GlobalParameter::query()->create([
            'key' => $parameter->key,
            'type' => $parameter->type->value,
            'value' => $value,
            'description' => $parameter->description,
            'valid_from' => $today,
            'changed_by' => Auth::id(),
        ]);
    }

    /**
     * @param array<string, string|null> $input
     * @param Collection<string, GlobalParameter> $current
     * @return array<string, list<string>>
     */
    private function validate(array $input, Collection $current): array
    {
        $errors = [];

        foreach ($input as $key => $value) {
            /** @var GlobalParameter|null $parameter */
            $parameter = $current->get($key);

            if ($parameter === null) {
                $errors[$key] = ['Nie ma takiego parametru.'];
                continue;
            }

            $rules = match ($parameter->type) {
                GlobalParameterType::NUMBER => ['nullable', 'numeric', 'min:0'],
                GlobalParameterType::PERCENT => ['nullable', 'numeric', 'min:0', 'max:1000'],
                GlobalParameterType::IBAN => ['nullable', 'string', 'regex:/^(PL)?[0-9\s]{26,34}$/'],
                GlobalParameterType::CHOICE => ['required', Rule::in(GlobalParameter::choicesFor($key))],
                default => ['nullable', 'string', 'max:1000'],
            };

            $validator = Validator::make([$key => $value], [$key => $rules]);

            if ($validator->fails()) {
                $errors[$key] = $validator->errors()->get($key);
                continue;
            }

            $placeholderError = $this->validatePlaceholders($parameter, $value, $current);

            if ($placeholderError !== null) {
                $errors[$key] = [$placeholderError];
            }
        }

        return $errors;
    }

    /**
     * Szablon moze odwolywac sie tylko do istniejacych parametrow.
     * To pilnuje, zeby tekst drukowany klientowi nie rozjechal sie
     * z wartoscia, ktorej pilnuje system - w starym systemie waznosc
     * oferty wynosila 10 dni w polu i 7 dni w tekscie.
     *
     * @param Collection<string, GlobalParameter> $current
     */
    private function validatePlaceholders(GlobalParameter $parameter, ?string $value, Collection $current): ?string
    {
        if ($parameter->type !== GlobalParameterType::TEMPLATE || $value === null) {
            return null;
        }

        preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', $value, $matches);

        foreach ($matches[1] as $placeholder) {
            if (!$current->has($placeholder)) {
                return sprintf('Szablon odwołuje się do nieistniejącego parametru "%s".', $placeholder);
            }
        }

        return null;
    }

    /** @return Collection<int, GlobalParameter> */
    private function effective(Carbon $date): Collection
    {
        /** @var Collection<int, GlobalParameter> $parameters */
        $parameters = GlobalParameter::query()
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderBy('id')
            ->get();

        return $parameters;
    }
}
