<?php

declare(strict_types=1);

namespace Salvon\Controller;

use Closure;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Salvon\Repository\RepositoryResult;
use Salvon\Validator\ValidationException;
use Throwable;
use Salvon\Service\Env;
use Salvon\Enum\Http\Field;
use Salvon\Enum\Http\Status;
use Illuminate\Http\JsonResponse;
use Salvon\Repository\QueryParam;
use Symfony\Component\HttpFoundation\Response;
use Salvon\Controller\Composite\ControllerSupport;
use Spatie\LaravelData\Exceptions\CannotCreateData;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class ApiController extends Controller
{
    use ControllerSupport;

    final protected function jsonResponse(?array $data = null, int $code = Response::HTTP_OK, array $headers = []): JsonResponse
    {
        return response()->json($data, $code, $headers);
    }

    protected function createResponsePreset(Status|string $status, ?string $message = null, ?array $data = []): array
    {
        $pattern = [
            Field::STATUS->value => $status instanceof Status ? $status->value : $status,
        ];

        if (!is_null($message)) {
            $pattern[Field::MESSAGE->value] = $message;
        }

        if (!is_null($data)) {
            $pattern[Field::DATA->value] = $data;
        }

        return $pattern;
    }

    final protected function secure(Closure $result): JsonResponse|BinaryFileResponse|StreamedResponse
    {
        try {
            return $result();
        } catch (CannotCreateData $exception) {
            $this->logError($exception);
            return $this->badRequestResponse('validation.data_cant_be_deserialized');
        } catch (ValidationException $exception) {
            return $this->validationResponse($exception->getValidationErrors());
        } catch (ThrottleRequestsException) {
            return $this->throttleResponse();
        }  catch (BadRequestHttpException $exception) {
            return $this->badRequestResponse($exception->getMessage());
        } catch (Throwable $throwable) {
            return $this->errorResponse($throwable);
        }
    }

    final protected function errorResponse(Throwable $throwable): JsonResponse
    {
        $this->logError($throwable);

        $statusCode = $this->determineStatusCode($throwable);

        $data = [Field::STATUS->value => $this->determineErrorStatus($statusCode)];

        if (Env::isDev()) {
            $data[Field::EXCEPTION->value] = $throwable::class;
            $data[Field::FILE->value] = $throwable->getFile();
            $data[Field::LINE->value] = $throwable->getLine();
            $data[Field::MESSAGE->value] = $throwable->getMessage();
            $data[Field::TRACE->value] = $throwable->getTrace();
        }

        return $this->jsonResponse(
            $data,
            $statusCode,
        );
    }

    final protected function dataResponse(array $data): JsonResponse
    {
        return $this->jsonResponse(
            $this->createResponsePreset(Status::OK, null, $data),
        );
    }

    final protected function jsonPresetResponse(Status $status, ?int $code = Response::HTTP_OK, ?string $message = null, ?array $data = null): JsonResponse
    {
        return $this->jsonResponse(
            $this->createResponsePreset($status, $message, $data),
            $code,
        );
    }

    final protected function repositoryListResponse(Request $request, RepositoryResult $result): JsonResponse
    {
        $queryParam = QueryParam::fromRequest($request);
        return $this->listResponse($queryParam, $result->items->toArray(), $result->count);
    }

    final protected function listResponse(QueryParam $queryParam, array $data, int $count): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::OK,
            data: [
                'items' => $data,
                'count' => $count,
                'page' => $queryParam->page,
                'limit' => $queryParam->limit,
            ],
        );
    }

    final protected function successResponse(): JsonResponse
    {
        return $this->jsonPresetResponse(status: Status::SUCCESS);
    }

    final protected function createdResponse(?string $message = null, ?array $data = null): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::CREATED,
            code: Response::HTTP_CREATED,
            message: $message,
            data: $data,
        );
    }

    final protected function updatedResponse(?string $message = null, ?array $data = null): JsonResponse
    {
        return $this->jsonPresetResponse(status: Status::UPDATED, message: $message, data: $data);
    }

    final protected function deletedResponse(): JsonResponse
    {
        return $this->jsonPresetResponse(status: Status::DELETED);
    }

    final protected function forbiddenResponse(): JsonResponse
    {
        return $this->jsonPresetResponse(status: Status::FORBIDDEN, code: Response::HTTP_FORBIDDEN);
    }

    final protected function notFoundResponse(): JsonResponse
    {
        return $this->jsonPresetResponse(status: Status::NOT_FOUND, code: Response::HTTP_NOT_FOUND);
    }

    final protected function noContentResponse(): JsonResponse
    {
        return $this->jsonResponse(code: Response::HTTP_NO_CONTENT);
    }

    final protected function badRequestResponse(?string $message, ?array $data = null): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::BAD_REQUEST,
            code: Response::HTTP_BAD_REQUEST,
            message: $message,
            data: $data,
        );
    }

    final protected function mfaRequiredResponse(?string $message = null, ?array $data = null): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::MFA_REQUIRED,
            code: Response::HTTP_BAD_REQUEST,
            message: $message,
            data: $data,
        );
    }

    final protected function invalidMfaResponse(?string $message = null, ?array $data = null): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::MFA_INVALID,
            code: Response::HTTP_BAD_REQUEST,
            message: $message,
            data: $data,
        );
    }

    final protected function unauthorizedResponse(?string $message = null): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::UNAUTHORIZED,
            code: Response::HTTP_UNAUTHORIZED,
            message: $message,
        );
    }

    final protected function throttleResponse(): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::THROTTLE,
            code: Response::HTTP_TOO_MANY_REQUESTS,
        );
    }

    final protected function validationResponse(array $errors): JsonResponse
    {
        return $this->jsonPresetResponse(
            status: Status::VALIDATION_ERROR,
            code: Response::HTTP_BAD_REQUEST,
            data: $errors,
        );
    }

    final protected function fileResponse(mixed $file): BinaryFileResponse
    {
        return response()->file($file);
    }

    final protected function downloadResponse(mixed $file): BinaryFileResponse
    {
        return response()->download($file);
    }

    final protected function streamedResponse(Closure $callback, int $status, array $headers): StreamedResponse
    {
        return response()->stream($callback, $status, $headers);
    }

    protected function buildPermissionMiddleware(string $permission, ?string $subPermission = null): string
    {
        return sprintf('permission:%s', permission($permission, $subPermission));
    }

    protected function protect(array $methods, string $permission, ?string $subPermission = null): void
    {
        $this->middleware(
            $this->buildPermissionMiddleware($permission, $subPermission),
            ['only' => $methods],
        );
    }
}
