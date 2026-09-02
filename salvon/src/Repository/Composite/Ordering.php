<?php

declare(strict_types=1);

namespace Salvon\Repository\Composite;

use ArgumentCountError;
use Salvon\Model\Model;
use Salvon\Service\Str;
use Salvon\Repository\QueryParam;
use Illuminate\Database\Eloquent\Builder;

trait Ordering
{
    //Add ordering to query builder, includes relational ordering
    protected function addOrdering(Builder $queryBuilder, QueryParam $queryParam, array $extraOrderableFields = ['id', 'created_at']): void
    {
        if (str_contains($queryParam->orderBy, '.')) {
            $this->addRelationalOrdering($queryBuilder, $queryParam);
            return;
        }

        $orderExists = in_array(strtolower($queryParam->orderBy), $this->getOrderableFields($queryBuilder, $extraOrderableFields), true);

        if (!$orderExists && in_array('position', $this->getOrderableFields($queryBuilder, $extraOrderableFields), true)) {
            $queryBuilder->orderBy('position');
        }

        if ($orderExists) {
            $queryBuilder->orderBy($queryParam->orderBy, $queryParam->order);
        }
    }

    //Order entries by relational field
    protected function legacy_addRelationalOrdering(Builder $queryBuilder, QueryParam $queryParam): void
    {
        $orderingParts = explode('.', $queryParam->orderBy);

        if (count($orderingParts) > 2) {
            throw new ArgumentCountError(
                'Relational ordering is supported only in top level relations.',
            );
        }

        [$relation, $column] = $orderingParts;

        //Due to DTO snake case mapper relation name might be sent as snake_case, try camel
        if (!$queryBuilder->getModel()->isRelation($relation)) {
            $relation = Str::camel($relation);
        }

        //In case that camel isn't working try pascal
        if (!$queryBuilder->getModel()->isRelation($relation)) {
            $relation = Str::pascal($relation);
        }

        if ($queryBuilder->getModel()->isRelation($relation)) {
            // @phpstan-ignore-next-line
            $queryBuilder->orderByLeftPowerJoins(sprintf('%s.%s', $relation, $column), $queryParam->order);

            /**
             * When ordering with relational table there is need to add ordering by primary table id.
             * If there are records with the same property value,
             * the order of these records might change between pages,
             * leading to duplicates or missing entries
             */
            $queryBuilder->orderByDesc('id');
        }
    }

    protected function addRelationalOrdering(Builder $queryBuilder, QueryParam $queryParam): void
    {
        $orderingParts = explode('.', $queryParam->orderBy);
        $column = array_pop($orderingParts);
        $relationPath = [];
        /** @var Model $model */
        $model = $queryBuilder->getModel();

        foreach ($orderingParts as $orderingPart) {
            $resolved = $model->findRelationRealName($orderingPart);

            if (!$resolved) {
                throw new \InvalidArgumentException(sprintf('Relation %s does not exists.', $orderingPart));
            }

            $relationPath[] = $resolved;
            $model = $model->{$resolved}()->getRelated();
        }

        $relation = implode('.', $relationPath);

        $queryBuilder->orderByLeftPowerJoins(sprintf('%s.%s', $relation, $column), $queryParam->order);
    }

    //Get fields that can be ordered
    protected function getOrderableFields(Builder $queryBuilder, array $extraOrderableFields): array
    {
        return array_merge($queryBuilder->getModel()->getFillable(), $extraOrderableFields);
    }
}
