<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AlertRule;
use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Alerts\AlertRuleService;
use App\Services\Alerts\AlertAcknowledgement;

/**
 * Reguły alertów — konfiguracja, nie odczyt alertów.
 *
 * Same alerty jadą razem z danymi, których dotyczą: lista zleceń niesie
 * znaczniki w wierszach, pulpit pasmo. Za **reguły** odpowiada `alerts`,
 * bo tam zmienia się konfiguracja.
 *
 * **Odhaczenie jest osobną sprawą i nie ma stałego uprawnienia.**
 * „Wiem o tym" to decyzja o rzeczy, której alert dotyczy — o zleceniu,
 * o stanie magazynu, o partii w piecu — więc wymagane uprawnienie
 * zależy od **wiersza**, a nie od trasy. Pośrednik od dostępu do modułu
 * tego nie wyprowadzi, bo czyta deklaracje kontrolera; dlatego tych
 * dwóch akcji nie chroni `protect()`, tylko `AlertAcknowledgement::may()`,
 * który zna wystąpienie i pyta o oba poziomy naraz.
 *
 * To jedyne miejsce w aplikacji, gdzie sprawdzenie nie jest
 * wyprowadzone z trasy — i jest to świadome, bo trasa nie ma z czego
 * go wyprowadzić.
 */
class AlertController extends ApiController
{
    public function __construct(
        private readonly AlertRuleService $service,
        private readonly AlertAcknowledgement $acknowledgement,
    ) {
        $this->protect(['board'], Permission::ALERTS->value, SubPermission::LIST->value);
        $this->protect(['create'], Permission::ALERTS->value, SubPermission::CREATE->value);
        $this->protect(['update'], Permission::ALERTS->value, SubPermission::UPDATE->value);
        $this->protect(['delete'], Permission::ALERTS->value, SubPermission::DELETE->value);
    }

    /** „Wiem o tym" — alert milknie w licznikach, ale zostaje w wierszu. */
    public function acknowledge(int $occurrence): JsonResponse
    {
        return $this->secure(function () use ($occurrence): JsonResponse {
            $result = $this->acknowledgement->acknowledge($occurrence);

            if ($result['denied']) {
                return $this->forbiddenResponse();
            }

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    /** Cofnięcie odhaczenia — ma być tak samo tanie jak odhaczenie. */
    public function revoke(int $occurrence): JsonResponse
    {
        return $this->secure(function () use ($occurrence): JsonResponse {
            $result = $this->acknowledgement->revoke($occurrence);

            if ($result['denied']) {
                return $this->forbiddenResponse();
            }

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->updatedResponse();
        });
    }

    public function board(): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->dataResponse($this->service->board()));
    }

    public function create(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->create($input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse($this->service->board());
        });
    }

    public function update(Request $request, AlertRule $rule): JsonResponse
    {
        return $this->secure(function () use ($request, $rule): JsonResponse {
            /** @var array<string, mixed> $input */
            $input = $request->all();

            $result = $this->service->update($rule, $input);

            if ($result['errors'] !== []) {
                return $this->validationResponse($result['errors']);
            }

            return $this->dataResponse($this->service->board());
        });
    }

    public function delete(AlertRule $rule): JsonResponse
    {
        return $this->secure(function () use ($rule): JsonResponse {
            $this->service->delete($rule);

            return $this->dataResponse($this->service->board());
        });
    }
}
