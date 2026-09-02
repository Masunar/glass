<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
use App\Enum\MinPriceCheck;
use App\Enum\SurchargeMode;
use App\Enum\GlobalParameterType;
use Illuminate\Database\Eloquent\Builder;

/**
 * Parametr wzoru wyceny albo tekst ofertowy.
 *
 * Typowany i wersjonowany: zmiana dopłaty za kształt z 35 na 40 zmienia
 * ceny wszystkich nowych ofert, a w starym systemie nie zostawiała
 * żadnego śladu — kto, kiedy i dlaczego.
 *
 * @property string $key
 * @property GlobalParameterType $type
 * @property string|null $value
 * @property string|null $description
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class GlobalParameter extends Dateable
{
    /**
     * Parametry typu CHOICE i enumy opisujące ich dopuszczalne wartości.
     *
     * Rejestr stoi tutaj, a nie w formularzu, bo zamknięta lista jest
     * regułą bazy, nie ozdobą ekranu — wpisanie czegokolwiek innego
     * cicho wyłączyłoby dopłatę zamiast zgłosić błąd.
     *
     * @var array<string, class-string<\BackedEnum>>
     */
    public const CHOICES = [
        'surcharge_mode' => SurchargeMode::class,
        'min_price_check' => MinPriceCheck::class,
    ];

    protected $table = 'global_parameters';

    protected $fillable = ['key', 'type', 'value', 'description', 'valid_from', 'valid_to', 'changed_by'];

    protected function casts(): array
    {
        return [
            'type' => GlobalParameterType::class,
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public static function value(string $key, ?Carbon $date = null): ?string
    {
        $date ??= Carbon::today();

        /** @var self|null $parameter */
        $parameter = self::query()
            ->where('key', $key)
            ->whereDate('valid_from', '<=', $date)
            ->where(static function (Builder $query) use ($date): void {
                $query->whereNull('valid_to')->orWhereDate('valid_to', '>=', $date);
            })
            ->orderByDesc('valid_from')
            ->first();

        return $parameter?->value;
    }

    /**
     * Dopuszczalne wartości parametru — pusta lista dla parametrów swobodnych.
     *
     * @return list<string>
     */
    public static function choicesFor(string $key): array
    {
        $enum = self::CHOICES[$key] ?? null;

        if ($enum === null) {
            return [];
        }

        return array_map(static fn(\BackedEnum $case): string => (string) $case->value, $enum::cases());
    }

    public static function number(string $key, ?Carbon $date = null): ?float
    {
        $value = self::value($key, $date);

        return $value === null ? null : (float) $value;
    }
}
