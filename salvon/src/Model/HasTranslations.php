<?php

namespace Salvon\Model;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property class-string<Model> $translationsModel
 */
trait HasTranslations
{
    public function translations(): HasMany
    {
        $foreignKey = Str::snake(class_basename($this)) . '_id';

        return $this->hasMany($this->translationsModel, $foreignKey, 'id');
    }
}
