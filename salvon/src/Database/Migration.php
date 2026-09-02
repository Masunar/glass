<?php

declare(strict_types=1);

namespace Salvon\Database;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration as LaravelMigration;

abstract class Migration extends LaravelMigration
{
    /** @param class-string[] $seeders */
    public function seed(array $seeders): void
    {
        each($seeders, fn(string $seeder) => $this->call($seeder));
    }

    public function translations(string $for, ?\Closure $columns = null, ?string $uniqueKeyName = null): void
    {
        $singular = Str::singular($for);
        $foreignKey = $singular . '_id';
        $tableName = $singular . '_translations';

        Schema::create($tableName, function (Blueprint $table) use ($for, $foreignKey, $columns, $uniqueKeyName) {
            $table->id();
            $table->foreignId($foreignKey)->references('id')->on($for)->cascadeOnDelete();
            $table->foreignId('language_id')->references('id')->on('languages');
            $table->string('name');

            if ($columns !== null) {
                $columns($table);
            }

            $table->unique([$foreignKey, 'language_id'], $uniqueKeyName);
        });
    }

    public function dropTranslations(string $for): void
    {
        Schema::dropIfExists(Str::singular($for) . '_translations');
    }

    /** @param class-string $seeder */
    private function call(string $seeder): void
    {
        $seeder = new $seeder();

        if (!$seeder instanceof Seeder) {
            throw new \TypeError('Migration seeder must be instance of Salvon\Database\Seeder');
        }

        $seeder->run();
    }
}
