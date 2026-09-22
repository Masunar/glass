<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

/**
 * Oferta jako zapisany dokument, nie wydruk skladany w locie.
 *
 * Roznica jest cala trescia tej tabeli. Wydruk z biezacego stanu
 * zlecenia nie potrafi odpowiedziec na pytanie „co klient dostal
 * w zeszlym tygodniu", bo po zmianie wyceny pokaze juz co innego.
 * Dlatego oferta trzyma **wlasna migawke** tresci i kwot z chwili
 * wystawienia i nigdy jej nie przelicza.
 *
 * Numer to `numer zlecenia / kolejny` (24046/1). Czlon po ukosniku
 * rosnie przy kazdym wystawieniu, wiec **jest jednoczesnie wersja** —
 * nie ma osobnego drzewa wersji, bo nie ma czego rozgalezic.
 * `sequence` jest unikalne w obrebie zlecenia i baza tego pilnuje:
 * dwie oferty 24046/2 to dwa rozne dokumenty pod jednym numerem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            // Numer widoczny dla klienta sklada sie z numeru zlecenia
            // i tej liczby. Trzymamy sam licznik, a nie gotowy napis:
            // numer zlecenia jest juz w bazie raz i nie ma powodu
            // przepisywac go w drugie miejsce, gdzie moze sie rozjechac.
            $table->unsignedSmallInteger('sequence');

            $table->string('status', 20);
            $table->string('detail_level', 20);
            $table->string('sum_mode', 20);
            $table->string('price_display', 20);

            // Czy w migawce byla wiecej niz jedna lista alternatywna.
            // Liczone w chwili wystawienia, bo pozniej role list moga
            // sie zmienic, a pytanie brzmi „czy **ta** oferta byla
            // wariantowa" (Z-Z-03).
            $table->boolean('is_variant')->default(false);

            $table->date('valid_until')->nullable();

            // Kwoty z chwili wystawienia. `gross` jest nullowalne:
            // zlecenie z kwota bez znanej stawki VAT nie ma brutto,
            // a zero byloby klamstwem (`OrderTotals`).
            $table->decimal('net', 12, 2);
            $table->decimal('vat', 12, 2)->nullable();
            $table->decimal('gross', 12, 2)->nullable();

            // Pelna tresc dokumentu: listy, pozycje, rozbicie na stawki,
            // teksty ofertowe ze slownika. JSON, bo to zamrozony
            // dokument — nikt po nim nie filtruje ani nie sumuje.
            $table->json('snapshot');

            $table->text('comment')->nullable();
            $table->string('rejection_reason', 200)->nullable();

            // Ktory wariant klient przyjal. Nullowalne takze przy
            // ofercie przyjetej: oferta bez alternatyw nie ma czego
            // wskazywac.
            $table->foreignId('accepted_list_id')->nullable()
                ->constrained('order_lists')->nullOnDelete();

            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'sequence']);
            $table->index(['status', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
