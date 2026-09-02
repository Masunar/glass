<?php

declare(strict_types=1);

namespace Salvon\Controller\Composite;

use Throwable;
use Salvon\Facade\Error;
use Salvon\Enum\Http\Status;
use Illuminate\Support\Facades\Log;
use Salvon\Factory\StatusCodeFactory;
use Symfony\Component\HttpFoundation\Response;

trait ControllerSupport
{
    final protected function abort(): never
    {
        Error::accessDenied();
    }

    final protected function notFound(): never
    {
        Error::notFound();
    }

    final protected function determineStatusCode(Throwable $throwable): int
    {
        return StatusCodeFactory::fromThrowable($throwable);
    }

    final protected function determineErrorStatus(int $code): Status
    {
        return match (true) {
            $code === Response::HTTP_NOT_FOUND => Status::NOT_FOUND,
            $code === Response::HTTP_FORBIDDEN || $code === Response::HTTP_UNAUTHORIZED => Status::ACCESS_DENIED,
            $code === Response::HTTP_METHOD_NOT_ALLOWED => Status::METHOD_NOT_ALLOWED,
            $code === Response::HTTP_NOT_ACCEPTABLE => Status::NOT_ACCEPTABLE,
            $code >= 400 && $code < 500 => Status::BAD_REQUEST,
            default => Status::ISE,
        };
    }

    final protected function logError(Throwable $throwable): void
    {
        Log::error($throwable->getMessage(), [$throwable->getFile(), $throwable->getLine()]);
    }
}
