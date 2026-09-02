<?php

declare(strict_types=1);

namespace Salvon\Facade;

use Exception;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use TypeError;
use Salvon\Enum\Http\Status;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final readonly class Error
{
    /**
     * @throws Exception
     */
    public static function throw(...$params): never
    {
        throw new Exception(...$params);
    }

    /**
     * @throws Exception
     */
    public static function ise(string $message = Status::ISE->value): never
    {
        self::throw(message: $message, code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function notFound(string $message = Status::NOT_FOUND->value): never
    {
        throw new NotFoundHttpException(message: $message, code: Response::HTTP_NOT_FOUND);
    }

    /**
     * @throws BadRequestHttpException
     */
    public static function badRequest(string $message = Status::BAD_REQUEST->value): never
    {
        throw new BadRequestHttpException($message);
    }

    public static function accessDenied(string $message = Status::ACCESS_DENIED->value): never
    {
        throw new AccessDeniedHttpException(message: $message, code: Response::HTTP_FORBIDDEN);
    }

    public static function unauthorized(string $message = Status::UNAUTHORIZED->value): never
    {
        throw new AccessDeniedHttpException(message: $message, code: Response::HTTP_UNAUTHORIZED);
    }

    public static function type(string $message): never
    {
        throw new TypeError(message: $message, code: Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public static function throttling(): never
    {
        throw new ThrottleRequestsException();
    }
}
