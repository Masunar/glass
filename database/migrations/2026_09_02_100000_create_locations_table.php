<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('locations', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('short_name', 20)->nullable();
            $table->string('color', 7)->nullable();

            $table->string('address_street', 150)->nullable();
            $table->string('address_house', 20)->nullable();
            $table->string('address_flat', 20)->nullable();
            $table->string('address_postal_code', 15)->nullable();
            $table->string('address_city', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            // czy w punkcie odbywa sie produkcja i czy klient moze tu odebrac
            $table->boolean('is_production')->default(false);
            $table->boolean('is_pickup_point')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);

            // identyfikator z systemu zrodlowego, do mapowania przy migracji
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        Schema::table('users', static function (Blueprint $table): void {
            $table->foreignId('location_id')
                ->nullable()
                ->after('phone')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('location_id');
        });

        Schema::dropIfExists('locations');
    }
};
