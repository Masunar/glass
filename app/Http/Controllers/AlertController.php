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
 * **Odhaczenie jest osobną sprawą i chodzi na `orders.update`.**
 * „Wiem o tym, dzwoniłem do klienta" to decyzja o zleceniu, a nie
 * o konfiguracji systemu — handlowiec musi móc to zrobić, nie mając
 * dostępu do ekranu reguł.
 *
 * ⚠️ To wiąże odhaczanie ze zleceniami. Gdy alerty wyjdą poza zlecenia
 * (A-02), uprawnienie trzeba będzie wyprowadzić z `alertable_type`.
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
        $this->protect(
            ['acknowledge', 'revoke'],
            Permission::ORDERS->value,
            SubPermission::UPDATE->value,
        );
    }

    /** „Wiem o tym" — alert milknie w licznikach, ale zostaje w wierszu. */
    public function acknowledge(int $occurrence): JsonResponse
    {
        return $this->secure(function () use ($occurrence): JsonResponse {
            $result = $this->acknowledgement->acknowledge($occurrence);

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
