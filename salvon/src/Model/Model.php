<?php

declare(strict_types=1);

namespace Salvon\Model;

use Override;
use Salvon\Service\Str;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model as LaravelModel;

/**
 * Abstract model prepared for multifunctional usage with default presets.
 *
 * Do not use with Pivot type tables!
 * Pivot Abstraction -> @Illuminate\Database\Eloquent\Model
 *
 * For model manipulation and searching use static @query() method or static @modelQuery()
 *
 * @property-read int $id
 * @property bool $enableDefaultCasting Enables default included data casting
 * @method static Model create(...$args)
 * @inheritDoc
 */
abstract class Model extends LaravelModel
{
    protected bool $enableDefaultCasting = true;

    private array $defaultCasts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    #[Override]
    public function getCasts(): array
    {
        if (!$this->enableDefaultCasting) {
            return parent::getCasts();
        }

        return [...$this->casts(), ...$this->defaultCasts];
    }

    public static function modelQuery(): Builder
    {
        return static::query();
    }

    public function findRelationRealName(string $relation): ?string
    {
        $cases = [
            $relation,
            Str::camel($relation),
            Str::pascal($relation),
            Str::studly($relation),
            Str::snake($relation),
        ];

        if (array_any($cases, fn() => $this->isRelation($relation))) {
            return $relation;
        }

        return null;
    }
}
