<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Strony aplikacji i paczki uprawnien.
 *
 * `app_pages` jest **rejestrem przypisan**, a nie katalogiem stron:
 * spis stron pochodzi z kodu (`AccessRegistry`), a ta tabela trzyma to,
 * co administrator moze zmienic bez wdrozenia — ktore uprawnienie
 * otwiera strone. Kolumna `permission_id` jest nullowalna, bo pulpit
 * jest dostepny kazdemu zalogowanemu, a brak przypisania to stan, ktory
 * ekran ma pokazac, a nie ukryc.
 *
 * Paczka uprawnien jest **wiazaniem, nie szablonem** (U-07): poprawka
 * w paczce natychmiast dziala we wszystkich rolach, ktore ja maja —
 * i dokladnie dlatego potrafi je zepsuc wszystkie naraz. Stad `pivot`
 * rola↔paczka zamiast kopiowania uprawnien przy nadaniu, i stad ekran
 * paczki musi pokazywac, kogo zmiana dotknie.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_pages', static function (Blueprint $table): void {
            $table->id();
            // Klucz z rejestru — po nim uzgadniamy wiersze przy kazdym
            // seedzie, wiec zmiana sciezki nie gubi przypisania.
            $table->string('code', 60)->unique();
            $table->string('path', 160);
            $table->string('module', 20)->nullable();
            $table->string('label', 120);
            $table->foreignId('permission_id')->nullable()
                ->constrained('permissions')->nullOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['module', 'position']);
        });

        Schema::create('permission_packages', static function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120)->unique();
            $table->string('description', 250)->nullable();
            $table->timestamps();
        });

        Schema::create('permission_package_items', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permission_package_id')
                ->constrained('permission_packages')->cascadeOnDelete();
            $table->foreignId('permission_id')
                ->constrained('permissions')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['permission_package_id', 'permission_id'], 'package_permission_unique');
        });

        Schema::create('role_permission_packages', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_package_id')
                ->constrained('permission_packages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['role_id', 'permission_package_id'], 'role_package_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_permission_packages');
        Schema::dropIfExists('permission_package_items');
        Schema::dropIfExists('permission_packages');
        Schema::dropIfExists('app_pages');
    }
};
