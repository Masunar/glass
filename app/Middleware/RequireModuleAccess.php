<?php

declare(strict_types=1);

namespace App\Middleware;

use Closure;
use App\Models\User;
use Illuminate\Http\Request;
use Salvon\Enum\Http\Field;
use Salvon\Enum\Http\Status;
use Illuminate\Routing\Route;
use App\Support\AccessRegistry;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dostęp do modułu jako **poziom nad dostępem do strony** (U-04).
 *
 * Bez tego `*.access` byłoby ozdobą ekranu konfiguracji: listwa
 * chowałaby moduł, a `orders.list` nadal otwierałoby API wklejonym
 * adresem albo skryptem. Poziom, który da się ominąć, nie jest
 * poziomem.
 *
 * **Wymaganie wyprowadzamy, nie wpisujemy.** Pośrednik czyta
 * uprawnienia, które kontroler już deklaruje przez `protect()`
 * (`permission:orders.list`), i przez `AccessRegistry::moduleOf()`
 * pyta o moduł. Gdyby wymaganie trzeba było dopisać przy każdej
 * trasie, prędzej czy później ktoś by go nie dopisał — i to właśnie
 * ta trasa zostałaby otwarta.
 *
 * Trasa bez uprawnień nie wymaga niczego: pulpit i logowanie mają być
 * dostępne, a brak deklaracji jest tu decyzją, nie przeoczeniem —
 * pilnuje tego `AccessCoverageTest`.
 */
class RequireModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $route = $request->route();

        if (!$user instanceof User || !$route instanceof Route) {
            // Uwierzytelnienie to osobne pietro. Odmowa dostepu
            // niezalogowanemu zamiast prosby o zalogowanie mylilaby
            // dwie rozne sytuacje.
            return $next($request);
        }

        foreach ($this->modulesOf($route) as $module) {
            $needed = $module . '.' . AccessRegistry::ACCESS;

            if ($user->can($needed)) {
                continue;
            }

            return response()->json([
                Field::STATUS->value => Status::FORBIDDEN->value,
                // Nazwa uprawnienia w odpowiedzi: front ma powiedziec,
                // czego brakuje, a nie „cos poszlo nie tak". Czlowiek
                // ma wyjsc z tego ekranu wiedzac, o co poprosic.
                Field::DATA->value => ['permission' => $needed, 'module' => $module],
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Moduły, do których należą uprawnienia chroniące tę trasę.
     *
     * @return list<string>
     */
    private function modulesOf(Route $route): array
    {
        $modules = [];

        foreach ($route->controllerMiddleware() as $entry) {
            if (!is_string($entry) || !str_starts_with($entry, 'permission:')) {
                continue;
            }

            $permission = substr($entry, strlen('permission:'));

            // Samo `zlec.access` nie wymaga siebie — inaczej ekran
            // konfiguracji nie dalby sie otworzyc bez dostepu do
            // modulu, ktory wlasnie sie konfiguruje.
            if (AccessRegistry::isModuleAccess($permission)) {
                continue;
            }

            $module = AccessRegistry::moduleOf($permission);

            if ($module !== null && !in_array($module, $modules, true)) {
                $modules[] = $module;
            }
        }

        return $modules;
    }
}
