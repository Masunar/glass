<?php

declare(strict_types=1);

namespace Salvon\Factory;

use Throwable;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

final readonly class StatusCodeFactory
{
    public static function fromThrowable(Throwable $throwable): int
    {
        $statusCode = $throwable->getCode();

        if (!array_key_exists($statusCode, Response::$statusTexts)) {
            return StatusCodeFactory::matchThrowableToCode($throwable);
        }

        return $statusCode;
    }

    public static function matchThrowableToCode(Throwable $throwable): int
    {
        return match (get_class($throwable)) {
            NotFoundHttpException::class, ModelNotFoundException::class => Response::HTTP_NOT_FOUND,
            MethodNotAllowedHttpException::class => Response::HTTP_METHOD_NOT_ALLOWED,
            UnauthorizedHttpException::class => Response::HTTP_UNAUTHORIZED,
            BadRequestHttpException::class => Response::HTTP_BAD_REQUEST,
            AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
            NotAcceptableHttpException::class => Response::HTTP_NOT_ACCEPTABLE,
            default => Response::HTTP_INTERNAL_SERVER_ERROR,
        };

    }
}
