# Glass — CRM dla zakładu szklarskiego

System zarządzania zleceniami w zakładzie szklarskim: od pierwszego kontaktu z klientem
i pomiaru u niego w domu, przez wycenę, produkcję i obróbkę szkła, po dostawę, montaż
i rozliczenie.

Projekt jest przepisaniem istniejącej aplikacji (Angular + Laravel, ~10 lat) na nowy stack.
Migracja danych z systemu produkcyjnego (~9 000 zleceń historycznych, ~140 w toku) jest
pełnoprawnym wymaganiem, nie dodatkiem.

Specyfikacja funkcjonalna modułów żyje w dokumentacji projektowej (`docs/`) — patrz
[`CLAUDE.md`](CLAUDE.md).

---

## Stack

| Warstwa | Technologia |
|---|---|
| Backend | PHP 8.5, Laravel 13, [Salvon](salvon/) (wewnętrzny framework RAD, `synteco/laravel-salvon`) |
| API / auth | Laravel Sanctum, MFA (google2fa), spatie/laravel-permission |
| Frontend | React 19, React Router 8 (SSR), TypeScript 6, MUI 9, Vite 8 |
| Realtime | Laravel Reverb (WebSocket) |
| Baza | MariaDB |
| Środowisko | Docker Compose (nginx, php-fpm, mariadb, phpMyAdmin, Mailpit) |
| Jakość kodu | PHPStan, Rector, PHP-CS-Fixer, PHPUnit, ESLint, Prettier |

Integracje dostarczane przez pakiety Salvon: GUS REGON, Google, geolokalizacja,
NBP, SMSAPI, Paynow, Przelewy24, UPS, BDO, Stooq, Imagicker, NordVPN.

---

## Wymagania

- Docker + Docker Compose
- `make`

Wszystko inne (PHP, Composer, Node) działa w kontenerach.

---

## Uruchomienie

```bash
# pełna instalacja: env dockera + kontenery + composer setup-dev (migracje + seedy + build front)
make init
```

Rozbite na kroki:

```bash
make docker-env-init   # docker/.docker.env z .example
make up                # start kontenerów
make app-init          # composer setup-dev wewnątrz kontenera app
```

Dev frontendu (HMR):

```bash
make dev               # kontenery + npm run dev
```

### Porty (domyślne, z `docker/.docker.env.example`)

| Usługa | Port |
|---|---|
| Aplikacja (nginx) | http://localhost:8080 |
| Frontend dev (React Router) | http://localhost:3000 |
| phpMyAdmin | http://localhost:8081 |
| Reverb (WS) | 8082 |
| Mailpit (UI) | http://localhost:8025 |

---

## Codzienna praca

```bash
make art c="migrate"        # dowolna komenda artisan
make migrate-fresh-seed     # przeładowanie bazy
make test                   # testy PHPUnit
make backend-analyse        # PHPStan
make backend-reformat       # Rector + PHP-CS-Fixer
make app-shell              # shell w kontenerze aplikacji
make mysql                  # klient mariadb
make queue                  # worker kolejki
make reverb                 # serwer WebSocket
```

Frontend (w `frontend/`):

```bash
npm run dev        # dev server
npm run build      # build produkcyjny
npm run typecheck  # react-router typegen + tsc
npm run pretty     # prettier
```

---

## Struktura

```
app/                Kod aplikacyjny (Controllers, Services, DTO, Validators, Models, Enum)
salvon/             Framework Salvon — src/ (rdzeń), packages/ (integracje), bundles/
config/             Konfiguracja Laravel + salvon.php, frontend_routes.php
database/           Migracje i seedy
routes/             api.php, auth.php, web.php, channels.php, console.php
frontend/           Aplikacja React Router (SSR)
  app/src/          Kod produktowy: api, auth, components, layout, routes, provider
  salvon/           Współdzielone komponenty/utils Salvon dla frontu
docker/             Dockerfile'e i konfiguracja nginx/php
server/             Konfiguracja serwera produkcyjnego (nginx)
```

---

## Konfiguracja lokalna

Pliki **nieśledzone** w gitcie, tworzone ze wzorców:

| Plik | Wzorzec |
|---|---|
| `.env` | `.env.example` |
| `docker/.docker.env` | `docker/.docker.env.example` |
| `frontend/env.ts` | `frontend/env.example.ts` |

`composer setup` / `make init` tworzą je automatycznie.

---

## Git

Gałąź główna: `main`. Praca na gałęziach funkcjonalnych — `Makefile` blokuje szybkie
commity (`make gc`, `make gcp`) bezpośrednio na `main`.
