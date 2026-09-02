# CLAUDE.md

Wskazówki dla Claude Code przy pracy w tym repozytorium.

## Czym jest ten projekt

**Glass** — CRM dla zakładu szklarskiego. Obsługuje pełny cykl zlecenia: kontakt z klientem →
pomiar u klienta → wycena → produkcja i obróbka szkła (w tym hartowanie podzlecane na zewnątrz) →
dostawa i montaż → rozliczenie.

Przepisujemy istniejącą aplikację (Angular + Laravel, ~10 lat) na nowy stack. **Migracja danych
jest wymaganiem** — w systemie produkcyjnym jest ~9 000 zleceń historycznych i ~140 w toku.

Repo jest na razie szkieletem: framework Salvon + auth/MFA/użytkownicy/uprawnienia.
Moduły domenowe dopiero powstają.

### Dokumentacja domenowa

Specyfikacja funkcjonalna żyje w **projekcie Claude „Szklo - CRM"** (`docs/00-przeglad.md`,
`10-zlecenia.md`, `20-hartownia.md`, `30-kontrahenci.md`, `40-magazyn.md`, `50-cennik.md`,
`60-uzytkownicy.md`, `70-produkcja.md`, `80-slowniki.md`, `90-projektanci.md`,
`100-raporty-finansowe.md`, `110-dostawy.md`, `120-statystyki.md`).

**Przed implementacją modułu domenowego przeczytaj odpowiedni dokument** — zawiera zasady
biznesowe, statusy, uprawnienia i pytania otwarte (`❓`) oraz miejsca odtwarzające obecne
zachowanie bez decyzji (`⚠️`).

Kluczowe pojęcia domenowe: zlecenie, pomiar (osobna encja z własną numeracją), formatka,
lista (grupa pozycji = alternatywa ofertowa), mix (materiał + grubość), schemat, zestaw,
sekcja cenowa, cena indywidualna, proces (operacja technologiczna z kodem literowym),
zlecenie zerowe.

Nazwy encji w kodzie mają wynikać ze słownika domenowego, nie z ogólnych określeń.

---

## Stack

- **Backend:** PHP 8.5, Laravel 13, Salvon (wewnętrzny framework RAD w `salvon/`)
- **Frontend:** React 19 + React Router 8 (SSR), TypeScript 6, MUI 9, Vite 8
- **Baza:** MariaDB · **Realtime:** Laravel Reverb · **Auth:** Sanctum + MFA (google2fa) +
  spatie/laravel-permission
- **Środowisko:** Docker Compose

## Komendy

Wszystko leci przez `make` (wykonuje w kontenerze `app`) — **nie uruchamiaj php/composer/npm
bezpośrednio na hoście**:

```bash
make init                  # pełna instalacja od zera
make up / down / restart   # kontenery
make dev                   # kontenery + frontend dev server
make art c="migrate"       # dowolna komenda artisan
make migrate-fresh-seed    # przeładowanie bazy z seedami
make test                  # PHPUnit
make backend-analyse       # PHPStan
make backend-reformat      # Rector + PHP-CS-Fixer  ← uruchom przed commitem
make app-shell             # shell w kontenerze
```

Frontend w `frontend/`: `npm run dev`, `npm run build`, `npm run typecheck`, `npm run pretty`.

---

## Architektura backendu

Warstwy — trzymaj się ich, to konwencja Salvona:

```
Controller  →  Service  →  Model / Repository
      ↑           ↑
  Validator     DTO
```

- **Controller** (`app/Http/Controllers/`) — cienki. CRUD dziedziczy po
  `Salvon\Controller\ApiCrudController`, każda akcja opakowana w `$this->secure(fn() => ...)`,
  odpowiedzi przez `createdResponse()` / `updatedResponse()` / `repositoryListResponse()`.
  Uprawnienia deklaratywnie: `protected ?string $permission` + `$this->protect([...], $permission, SubPermission::READ->value)`.
- **Service** (`app/Services/`) — `final readonly class`, cała logika biznesowa. Listy przez
  helper `repo_list($request, Model::class, ['filters' => ..., 'with' => ..., 'modify' => ...])`
  zwracający `RepositoryResult`.
- **Validator** (`app/Validators/`) — klasy dziedziczące `Salvon\Validator\Validator`,
  metoda `violations(SomeDTO $input): bool` z wywołaniami `$this->violation(pole, $input, reguły)`.
  W prostych przypadkach CRUD: helper `crud_validate($request, [...])`.
- **DTO** (`app/DTO/`) — spatie/laravel-data, wejście do walidatorów i serwisów.
- **Enum** (`app/Enum/`) — m.in. `Permission` (backed enum, wartości używane w kontrolerach).

**Zasady:**
- każdy plik PHP zaczyna się od `declare(strict_types=1);`
- typy na wszystkim: parametry, zwroty, właściwości
- `final` domyślnie; `readonly` dla serwisów i DTO
- constructor property promotion + `private readonly` dla zależności
- kod aplikacyjny w `App\`, kod wielokrotnego użytku w `Salvon\` (`salvon/src/`)

**Salvon** (`salvon/`) to osobny, współdzielony framework:
- `salvon/src/` — rdzeń (Api, Controller, Repository, Validator, Service, Eloquent, Generator…)
- `salvon/packages/` — integracje: GUS REGON, Google, geolokalizacja, NBP, SMSAPI, Paynow,
  Przelewy24, UPS, BDO, Stooq, Imagicker, NordVPN

Zmieniaj Salvona tylko gdy to naprawdę kod generyczny — logika domenowa szkła należy do `app/`.

## Routing

`routes/api.php` autoładuje katalog `routes/api/` przez `Route::loadApiDir()` (fasada
`Salvon\Facade\Route`) pod middlewarem `require_mfa`. Trasy auth: `routes/auth.php`.
Mapowanie tras frontu: `config/frontend_routes.php`.

## Architektura frontendu

```
frontend/app/
  root.tsx, root-layout.tsx, routes.ts   # wejście React Router (SSR)
  src/api/        # klienci HTTP (ApiRequest.ts + *Api.ts) — cały ruch do backendu tędy
  src/auth/       # user-loader.ts, user.ts
  src/components/ # komponenty produktowe (HasPermission.tsx do gatingu po uprawnieniach)
  src/layout/, src/provider/, src/router/, src/routes/, src/hook/, src/locale/, src/config/
frontend/salvon/  # współdzielone komponenty/utils Salvon (form, utils, …)
```

- i18next + react-i18next; **żadnych stringów UI na sztywno** — wszystko przez klucze tłumaczeń
  (język główny: polski)
- MUI jako system komponentów; nowe formularze na `react-hook-form` + `frontend/salvon/components/form`
- uprawnienia w UI przez `HasPermission`

## Zasady przekrojowe (z dokumentacji)

Wynikają ze specyfikacji i dotyczą całego systemu:

1. **Jeden słownik statusów** — zlecenie ma dokładnie jeden status; wszystkie moduły czytają
   z tego samego źródła (dziś w starym systemie nazwy się rozjeżdżają).
2. **Dane referencyjne ze Słowników** — rodzaje szkła, grubości, procesy, wykończenia,
   rodzaje wpłat, statusy. **Zero wartości zaszytych w kodzie.**
3. **Alerty konfigurowalne** — silnik reguł `warunek → etykieta → kolor → moduł`,
   edytowalny z panelu admina, nie zahardkodowany.
4. **Audyt** — zmiany istotne biznesowo logowane z wartością *przed → po*, agregowane
   w ramach jednej sesji edycji.
5. **Widoczność cen** — produkcja i montaż pracują na tych samych dokumentach z ukrytymi
   kolumnami cenowymi. Uprawnienia decydują o polach, nie tylko o ekranach.
6. **Dwie lokalizacje** (Stobno, Chopina) przenikają model danych — zakres rozdzielenia
   danych jest jeszcze pytaniem otwartym.

## Konfiguracja lokalna

Nieśledzone w gitcie, tworzone ze wzorców: `.env` ← `.env.example`,
`docker/.docker.env` ← `docker/.docker.env.example`, `frontend/env.ts` ← `frontend/env.example.ts`.
**Nigdy nie commituj tych plików ani żadnych sekretów.**

## Git

- gałąź główna `main`, praca na gałęziach funkcjonalnych
- przed commitem: `make backend-reformat`, `make backend-analyse`, `make test`
- `make gc` / `make gcp` (szybki commit „draft") celowo blokują się na `main`

### Operacje zdalne z Cowork

Środowisko Cowork nie ma dostępu do poświadczeń GitHuba z keychaina macOS. Push/fetch idzie
przez deploy key przypisany do tego repozytorium, opakowany w `.git/claude-git.sh`:

```bash
bash .git/claude-git.sh push origin main
bash .git/claude-git.sh fetch origin
```

Pliki `.git/claude_deploy_key`, `.git/claude_known_hosts` i `.git/claude-git.sh` leżą poza
drzewem roboczym (nie da się ich zacommitować) i nie są w klonie — dotyczą wyłącznie tej maszyny.
Zwykły `git push` z terminala działa jak dotąd, na kluczu użytkownika. Odwołanie dostępu:
https://github.com/Masunar/glass/settings/keys → Delete.
