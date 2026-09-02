<?php

namespace Salvon\Model;

use Exception;
use Carbon\Carbon;
use JsonException;
use Salvon\Facade\Error;
use Salvon\Enum\Log\LogAction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Requires manually addition of $logClass property to each model which do not use @Loggable abstraction
 * Traits do not support overriding for properties
 * see @Loggable
 */
trait HasLogs
{
    /**
     * @throws Exception
     */
    public static function createLog(int $modelId, LogAction|string|null $action, array|Arrayable|null $before = null, null|array|Arrayable $after = null, array|null $changes = null): void
    {
        if (!isset(static::$logClass)) {
            Error::throw('Static property $logClass was not initialized.');
        }

        if ($action instanceof LogAction) {
            $action = $action->value;
        }

        if ($before instanceof Arrayable) {
            $before = $before->toArray();
        }

        if ($after instanceof Arrayable) {
            $after = $after->toArray();
        }

        $logBuilder = static::createLogBuilder();

        if (!$logBuilder instanceof Builder) {
            Error::throw(sprintf('Model instance %s cannot be created for logging data.', static::$logClass));
        }

        $logBuilder->create([
            'executed_by' => Auth::id(),
            'model_id' => $modelId,
            'action' => $action,
            'changes' => $changes,
            'snapshot_before' => $before,
            'snapshot_after' => $after,
            'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
            'updated_at' => null,
        ]);
    }

    /** @return null|string|class-string<Log> */
    public static function getLoggingClass(): ?string
    {
        if (!isset(static::$logClass)) {
            return null;
        }

        return static::$logClass;
    }

    protected static function createLogBuilder(): ?Builder
    {
        $class = static::getLoggingClass();

        if ($class === null || $class === '') {
            return null;
        }

        /** @phpstan-ignore-next-line */
        /** @var $class Log */
        return $class::modelQuery();
    }

    /**
     * @throws JsonException
     */
    protected function applyCastsToChanges(array $changes): array
    {
        $casts = $this->getCasts();

        foreach ($casts as $key => $value) {
            if ($value !== 'array') {
                continue;
            }

            if (!isset($changes[$key])) {
                continue;
            }

            $changes[$key] = json_decode($changes[$key], true, 512, JSON_THROW_ON_ERROR);
        }

        return $changes;
    }
}
