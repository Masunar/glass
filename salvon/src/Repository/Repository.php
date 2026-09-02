<?php

declare(strict_types=1);

namespace Salvon\Repository;

use Illuminate\Http\Request;
use Salvon\Facade\Error;
use Salvon\Service\Instance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Salvon\Repository\Composite\Ordering;
use Salvon\Repository\Composite\Searching;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @template T of Model
 * @property class-string<T> $model
 */
class Repository
{
    use Searching;
    use Ordering;

    protected Builder $builder;

    /** @param class-string<T> $model */
    public function __construct(
        private readonly Request $request,
        private readonly string $model,
        private readonly int $defaultPerPage = 25,
        private readonly int $maxResults = 500,
    ) {
        if (!Instance::of($model, Model::class)) {
            Error::ise('Repository model must be instance of Laravel Model');
        }

        $this->builder = $this->query();
    }

    /**
     * Initialize repository
     * @param class-string<T> $model
     */
    public static function of(Request $request, string $model, int $defaultPerPage = 25, int $maxResults = 500): self
    {
        return new Repository($request, $model, $defaultPerPage, $maxResults);
    }

    /**
     * Create list from given model
     * @param class-string<T> $model
     */
    public static function list(Request $request, string $model, array $options = []): RepositoryResult
    {
        $filter = (array) ($options['filters'] ?? []);
        $with = (array) ($options['with'] ?? []);
        $trashed = boolval($options['trashed'] ?? false);
        $paginate = boolval($options['paginate'] ?? true);
        $defaultPerPage = intval($options['default_per_page'] ?? 25);
        $maxResults = intval($options['max_results'] ?? 500);
        $modify = $options['modify'] ?? null;
        $builder = self::of($request, $model, $defaultPerPage, $maxResults);

        $builder->order()->filter($filter);

        if ($trashed) {
            $builder->trashed();
        }

        if ($paginate) {
            $builder->paginate();
        }

        if (!empty($with)) {
            $builder->with($with);
        }

        if (is_callable($modify)) {
            $builder->modify($modify);
        }

        return $builder->result();
    }

    /** Add filters by pattern */
    public function filter(?array $filters = null): self
    {
        $queryParam = QueryParam::fromRequest($this->request, $this->defaultPerPage, $this->maxResults);

        $this->addIdsFilter($this->builder, $queryParam);
        $this->addNotIdsFilter($this->builder, $queryParam);
        $this->addIsActiveFilter($this->builder, $queryParam);
        $this->addSearchableFieldsFiltering($this->builder, $queryParam, $filters);

        return $this;
    }

    /** Add ordering to query builder */
    public function order(): self
    {
        $queryParam = QueryParam::fromRequest($this->request, $this->defaultPerPage, $this->maxResults);

        $this->addOrdering($this->builder, $queryParam);

        return $this;
    }

    /** Add custom query modifications */
    public function modify(callable $callback): self
    {
        $callback($this->builder);

        return $this;
    }

    /** Paginate items */
    public function paginate(): self
    {
        $queryParam = QueryParam::fromRequest($this->request, $this->defaultPerPage, $this->maxResults);

        if ($queryParam->limit === -1) {
            return $this;
        }

        $skip = $queryParam->page * $queryParam->limit;
        $this->builder->skip($skip)->limit($queryParam->limit);

        return $this;
    }

    /** Prepare final result */
    public function result(): RepositoryResult
    {
        return new RepositoryResult(
            $this->builder->getCountForPagination(),
            $this->builder->get(),
        );
    }

    /** Include trashed items - with soft deletes only */
    public function trashed(): self
    {
        if ($this->hasSoftDeletes()) {
            //@phpstan-ignore-next-line
            $this->builder->withTrashed();
        }

        return $this;
    }

    /** Include trashed items - with soft deletes only */
    public function with(array $with): self
    {
        $this->builder->with($with);

        return $this;
    }

    /**
     * Get Builder instance from model
     * @return Builder<T>
     */
    protected function query(): Builder
    {
        return $this->model::query();
    }

    /** Add table name prefix to column */
    protected function qualifyColumn(string $column): string
    {
        return $this->builder->qualifyColumn($column);
    }

    /** Check if model has relation */
    protected function hasRelation(string $relation): bool
    {
        return $this->getModelInstance()->isRelation($relation);
    }

    /**
     * Create empty model instance - internal purposes only
     * @return T
     */
    private function getModelInstance(): Model
    {
        return new $this->model();
    }

    /** Check if soft deletes are available */
    private function hasSoftDeletes(): bool
    {
        return in_array(SoftDeletes::class, class_uses($this->model));
    }
}
