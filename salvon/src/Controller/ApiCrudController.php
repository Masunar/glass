<?php

declare(strict_types=1);

namespace Salvon\Controller;

use Illuminate\Database\Eloquent\Model;
use Salvon\Enum\SubPermission;

abstract class ApiCrudController extends ApiController
{
    /** @var class-string<Model> $dtoClass */
    protected string $model;

    protected ?string $permission = null;

    public function __construct()
    {
        if ($this->permission === null || $this->permission === '') {
            return;
        }

        $this->middleware(
            $this->buildCrudPermission(SubPermission::LIST->value),
            ['only' => ['list']],
        );

        $this->middleware(
            $this->buildCrudPermission(SubPermission::READ->value),
            ['only' => ['read']],
        );

        $this->middleware(
            $this->buildCrudPermission(SubPermission::CREATE->value),
            ['only' => ['create']],
        );

        $this->middleware(
            $this->buildCrudPermission(SubPermission::UPDATE->value),
            ['only' => ['update']],
        );

        $this->middleware(
            $this->buildCrudPermission(SubPermission::DELETE->value),
            ['only' => ['delete']],
        );

        $this->middleware(
            $this->buildCrudPermission(SubPermission::RESTORE->value),
            ['only' => ['restore']],
        );
    }

    protected function buildCrudPermission(string $subPermission): string
    {
        return $this->buildPermissionMiddleware($this->permission, $subPermission);
    }
}
