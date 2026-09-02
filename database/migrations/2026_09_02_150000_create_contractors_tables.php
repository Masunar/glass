<?php

declare(strict_types=1);

use Salvon\Database\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contractors', static function (Blueprint $table): void {
            $table->id();

            // Typ jawnie, a nie wyprowadzany z obecnosci NIP-u. Od niego
            // zalezy walidacja, dokument sprzedazy i obowiazki RODO, wiec
            // rozpoznawanie po pustym polu jest kruche.
            $table->string('type', 10);

            $table->string('name', 200);
            $table->string('short_name', 60)->nullable();
            $table->string('tax_id', 20)->nullable();
            $table->string('registry_id', 20)->nullable();

            $table->string('first_name', 60)->nullable();
            $table->string('last_name', 60)->nullable();

            // Zadne z pol kontaktowych nie jest wymagane. W starej bazie
            // telefon "0" i e-mail "123" powtarzaja sie w wielu rekordach,
            // bo pola byly obowiazkowe, wiec uzupelniano je czymkolwiek.
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->string('website', 120)->nullable();

            $table->unsignedSmallInteger('payment_days')->default(0);
            $table->decimal('credit_limit', 12, 2)->default(0);

            // Ta sama firma bywa jednoczesnie klientem i dostawca szkla,
            // wiec dostawca jest flaga, a nie osobna kartoteka.
            $table->boolean('is_supplier')->default(false);
            $table->boolean('is_active')->default(true);

            $table->text('note')->nullable();
            $table->date('registered_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->timestamps();

            $table->index('name');
            $table->index('tax_id');
            $table->index(['is_active', 'name']);
        });

        // Trzy adresy zamiast jednego: firma z centrala w Warszawie
        // odbiera szklo w Szczecinie, a fakture dostaje gdzie indziej.
        Schema::create('contractor_addresses', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('kind', 20);

            $table->string('country', 2)->default('PL');
            $table->string('voivodeship', 60)->nullable();
            $table->string('county', 60)->nullable();
            $table->string('post_office', 60)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('street', 120)->nullable();
            $table->string('building_number', 20)->nullable();
            $table->string('unit_number', 20)->nullable();
            $table->timestamps();

            $table->unique(['contractor_id', 'kind']);
        });

        Schema::create('contractor_contacts', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('first_name', 60)->nullable();
            $table->string('last_name', 60)->nullable();
            $table->string('position', 80)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('email', 120)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['contractor_id', 'is_primary']);
        });

        // Poziom 2 ustalania ceny: sekcja cenowa per sekcja asortymentu.
        Schema::create('contractor_price_sections', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('section', 20);
            $table->foreignId('price_section_id')->constrained('price_sections')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['contractor_id', 'section']);
        });

        // Poziom 3: nadpisanie ceny konkretnego produktu dla kontrahenta.
        // Wersjonowane, bo bez dat obowiazywania promocja zostaje na zawsze.
        Schema::create('contractor_prices', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->decimal('net_price', 12, 2);
            $table->date('valid_from');
            $table->date('valid_to')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_id', 'product_id', 'valid_from'], 'contractor_prices_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_prices');
        Schema::dropIfExists('contractor_price_sections');
        Schema::dropIfExists('contractor_contacts');
        Schema::dropIfExists('contractor_addresses');
        Schema::dropIfExists('contractors');
    }
};
