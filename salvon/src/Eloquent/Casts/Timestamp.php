<?php

declare(strict_types=1);

namespace Salvon\Eloquent\Casts;

use Override;
use Carbon\Carbon;
use Salvon\Enum\DateTimeFormat;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class Timestamp implements CastsAttributes
{
    #[Override]
    public function get($model, $key, $value, $attributes): ?string
    {
        if (is_string($value)) {
            $date = Carbon::parse($value);
            return $date->format($this->getFormat());
        }

        if ($value instanceof Carbon) {
            return $value->format($this->getFormat());
        }

        return null;
    }

    #[Override]
    public function set($model, $key, $value, $attributes): ?Carbon
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value;
        }

        $dateFormat = Config::get('salvon.framework.default_cast_date_format', DateTimeFormat::DEFAULT->value);
        return Carbon::createFromFormat($dateFormat, $value);
    }

    private function getFormat(): string
    {
        return Config::get('salvon.framework.default_cast_date_format', DateTimeFormat::DEFAULT->value);
    }
}
