<?php

namespace App\Middleware;

use Closure;
use App\Services\Auth\Mfa;
use Illuminate\Http\Request;
use Salvon\Enum\Http\Field;
use Salvon\Enum\Http\Status;
use Symfony\Component\HttpFoundation\Response;

class RequireMfa
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && Mfa::requestLocked($request->user())) {
            return response()->json([
                Field::STATUS->value => Status::MFA_REQUIRED->value,
            ], status: Response::HTTP_BAD_REQUEST);
        }

        return $next($request);
    }
}
