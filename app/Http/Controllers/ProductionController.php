<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enum\Permission;
use Illuminate\Http\Request;
use Salvon\Enum\SubPermission;
use Illuminate\Http\JsonResponse;
use Salvon\Controller\ApiController;
use App\Services\Production\TaskExecution;
use App\Services\Production\ProductionQueue;

/**
 * Kolejka stanowiska i odhaczanie etapów.
 *
 * Osobny kontroler, nie kolejne metody w zleceniach: to jest ekran dla
 * innego człowieka, z innym uprawnieniem i bez dostępu do kwot.
 */
class ProductionController extends ApiController
{
    public function __construct(
        private readonly ProductionQueue $queue,
        private readonly TaskExecution $execution,
    ) {
        $this->protect(['board'], Permission::PRODUCTION->value, SubPermission::LIST->value);
        $this->protect(
            ['start', 'finish', 'issue', 'reopen'],
            Permission::PRODUCTION->value,
            SubPermission::UPDATE->value,
        );
    }

    public function board(Request $request): JsonResponse
    {
        return $this->secure(function () use ($request): JsonResponse {
            $workstation = $request->query('workstation');
            $process = $request->query('process');

            return $this->dataResponse($this->queue->board(
                // „none" to nie brak filtru, tylko filtr na etapy bez
                // stanowiska — te, których nikt nie przypisał w słowniku.
                is_numeric($workstation) ? (int) $workstation : null,
                $workstation === 'none',
                is_numeric($process) ? (int) $process : null,
                $request->boolean('done'),
            ));
        });
    }

    public function start(int $task): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->result($this->execution->start($task)));
    }

    public function finish(Request $request, int $task): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->result(
            $this->execution->finish($task, $request->input('note')),
        ));
    }

    public function issue(Request $request, int $task): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->result($this->execution->reportIssue(
            $task,
            $request->input('issue_type'),
            $request->input('note'),
        )));
    }

    /** Cofnięcie odhaczenia — terminal ma duże przyciski i pomyłki. */
    public function reopen(int $task): JsonResponse
    {
        return $this->secure(fn(): JsonResponse => $this->result($this->execution->reopen($task)));
    }

    /**
     * @param array{errors: array<string, list<string>>, status: string|null} $result
     */
    private function result(array $result): JsonResponse
    {
        if ($result['errors'] !== []) {
            return $this->validationResponse($result['errors']);
        }

        return $this->dataResponse(['status' => $result['status']]);
    }
}
