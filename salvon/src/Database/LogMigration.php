<?php

namespace Salvon\Database;

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

abstract class LogMigration extends Migration
{
    protected string $tableName;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create($this->tableName, function (Blueprint $table): void {
            self::insertSchema($table);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->tableName);
    }

    final public static function insertSchema(Blueprint $table): void
    {
        $table->id();
        $table->bigInteger('model_id')->nullable();
        $table->bigInteger('executed_by')->nullable();
        $table->bigInteger('executed_by_description')->nullable();
        $table->string('action');
        $table->json('changes')->nullable();
        $table->json('snapshot_before')->nullable();
        $table->json('snapshot_after')->nullable();
        $table->timestamps();
    }
}
