<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Salvon\Model\Dateable;
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
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 */
class GlobalParameter extends Dateable
{
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

    /** @param Builder<self> $query */
    public function scopeEffective(Builder $query, ?Carbon $date = null): Builder
    {
        $date ??= Carbon::today();

        $query->whereDate('valid_from', '<=', $date)
            ->where(static fn(Builder $inner) => $inner
                ->whereNull('valid_to')
                ->orWhereDate('valid_to', '>=', $date));

        return $query;
    }

    public static function value(string $key, ?Carbon $date = null): ?string
    {
        /** @var self|null $parameter */
        $parameter = self::query()->where('key', $key)->effective($date)->orderByDesc('valid_from')->first();

        return $parameter?->value;
    }

    public static function number(string $key, ?Carbon $date = null): ?float
    {
        $value = self::value($key, $date);

        return $value === null ? null : (float) $value;
    }
}
