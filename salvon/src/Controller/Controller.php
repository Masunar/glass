<?php

declare(strict_types=1);

namespace Salvon\Controller;

use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as LaravelRouterController;

abstract class Controller extends LaravelRouterController
{
    use AuthorizesRequests;
    use ValidatesRequests;
}
