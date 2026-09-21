<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Dostawca jako osobny slownik, nie kontrahent z flaga. Kartoteka
        // kontrahentow niesie limit kupiecki, sekcje cenowa i rabaty —
        // rzeczy, ktore przy dostawcy nic nie znacza i przy kazdym
        // przegladzie kartoteki trzeba by pomijac.
        Schema::create('suppliers', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('short_name', 20)->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->unique('name');
        });

        Schema::table('products', static function (Blueprint $table): void {
            // Nullowalne, bo wiekszosc katalogu nie ma dzis przypisanego
            // dostawcy i zgadywanie go byloby wymyslaniem danych.
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('product_group_id')
                ->constrained('suppliers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', static function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::dropIfExists('suppliers');
    }
};
