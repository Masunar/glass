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

/**
 * Reguły alertów — konfiguracja, nie odczyt alertów.
 *
 * Same alerty jadą razem z danymi, których dotyczą: lista zleceń niesie
 * znaczniki w wierszach, pulpit pasmo. Ten kontroler odpowiada wyłącznie
 * za **reguły**, więc chroni go `alerts`, a nie `orders`.
 */
class AlertController extends ApiController
{
    public function __construct(
        private readonly AlertRuleService $service,
    ) {
        $this->protect(['board'], Permission::ALERTS->value, SubPermission::LIST->value);
        $this->protect(['create'], Permission::ALERTS->value, SubPermission::CREATE->value);
        $this->protect(['update'], Permission::ALERTS->value, SubPermission::UPDATE->value);
        $this->protect(['delete'], Permission::ALERTS->value, SubPermission::DELETE->value);
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
