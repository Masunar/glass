<?php

declare(strict_types=1);

namespace Salvon\Repository\Composite;

use Closure;
use Exception;
use Salvon\Facade\Error;
use Salvon\Collection\TArray;
use Salvon\Repository\QueryParam;
use Salvon\Repository\Repository;
use Salvon\Repository\DatabaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Salvon\Enum\Eloquent\Searching\Owner;
use Salvon\Enum\Eloquent\Searching\MatchType;
use Salvon\Enum\Eloquent\Searching\DataSource;
use Salvon\Enum\Eloquent\Searching\Concatenation;

trait Searching
{
    /**
     * List of fields defined for automatic filtering
     *
     * Filtering pattern:
     * [ field_name:database_field => 'data_source:field_owner:match_type:query_concatenation_type']
     *
     * Pattern Explanation:
     * 1. field_name:database_field -> field name in request and field in database, use only with @DataSource::OTHER
     * !! Warning: if used pattern some_field instead of field_name:database_field field_name will be same as database_field
     * 2. data_source -> determines where in query was value (search_query or other param), by default @DataSource::SEARCH
     * 3. field_owner-> determines if field belongs to model or is relational, by default automatically determined
     * 4. match_type -> determines what action should be done in where statement, by default @MatchType::EXACT
     * 5. query_concatenation_type -> determines if where statement should be concatenated with "OR" or "AND", by default @Concatenation::OR
     * !! Warning: @Concatenation::OR should be used mostly with search_query searching, for scope narrowing use @Concatenation::AND
     *
     * Examples:
     * ['id' => 'search:own:exact:or'] -> search models by id using search_query param
     * ['id'] -> shorthand for same as above (using defaults without manually declaration), search model by id
     * ['id', 'name'] -> search models by name or id
     * ['company.name', name] -> search model by name or by field name in relation company
     * ['company:company.name' => 'other:relational:exact:and']
     * -- search other param "company" references name in company relation, match exactly this value, use andWhere in query --
     *
     * data_source: @var DataSource
     * field_owner: @var Owner
     * match_type: @var MatchType
     * query_concatenation_type: @var Concatenation
     *
     * Wanted combination does not exist?
     * @see Repository::addFilters()
     *
     * @throws Exception
     */
    protected function addSearchableFieldsFiltering(Builder $queryBuilder, QueryParam $queryParam, ?array $filters = null): void
    {
        if (!$filters) {
            $filters = ['id' => 'search:own:exact:or'];
        }

        if (empty($filters)) {
            return;
        }

        $queryBuilder->where(function (Builder $queryBuilder) use ($queryParam, $filters): void {
            $this->iterateThroughSearchableFields($queryBuilder, $queryParam, $filters);
        });
    }

    /** @throws Exception */
    protected function iterateThroughSearchableFields(Builder $queryBuilder, QueryParam $queryParam, ?array $filters): void
    {
        $orFilters = new TArray(DatabaseFilter::class);
        $andFilters = new TArray(DatabaseFilter::class);

        foreach ($filters as $field => $pattern) {
            if (is_int($field)) {
                $field = (string) $pattern;
            }

            $databaseFilter = $this->prepareDatabaseFilter($field, $pattern);

            if ($databaseFilter->concatenation === Concatenation::OR) {
                $orFilters->add($databaseFilter);
            } else {
                $andFilters->add($databaseFilter);
            }
        }

        //Group AND & OR filters separately with bracket to be sure they will apply conditions correctly
        $queryBuilder->where(function (Builder $queryBuilder) use ($queryParam, $orFilters): void {
            each($orFilters, function (DatabaseFilter $field) use ($queryBuilder, $queryParam): void {
                $this->buildSearchableFieldFiltering($queryBuilder, $queryParam, $field);
            });
        });

        $queryBuilder->where(function (Builder $queryBuilder) use ($queryParam, $andFilters): void {
            each($andFilters, function (DatabaseFilter $field) use ($queryBuilder, $queryParam): void {
                $this->buildSearchableFieldFiltering($queryBuilder, $queryParam, $field);
            });
        });

    }

    protected function prepareDatabaseFilter(string $field, string $pattern): DatabaseFilter
    {
        //Split request source field name and database column name, as default use primary name
        [$sourceFieldName, $databaseFieldName] = explode(':', $field) + [$field, $field];

        //Split pattern for each filtering option
        [$dataSource, $fieldOwner, $matchType, $concatenation] = explode(':', $pattern) + ['', '', '', ''];

        //Determines if field is owned by current table or by related for default usage
        $defaultOwner = str_contains($field, '.') ? Owner::RELATION : Owner::OWN;

        //Is filter value stored in search query or in other params?
        $dataSource = DataSource::tryFrom($dataSource) ?? DataSource::SEARCH;
        //Is column contained in current table or in related?
        $fieldOwner = Owner::tryFrom($fieldOwner) ?? $defaultOwner;
        //Resolve how to match value with condition
        $matchType = MatchType::tryFrom($matchType) ?? MatchType::LIKE;
        //Is filter condition concatenated with 'OR' or 'AND'?
        $concatenation = Concatenation::tryFrom($concatenation) ?? Concatenation::OR;

        return new DatabaseFilter(
            sourceFieldName: $sourceFieldName,
            databaseFieldName: $databaseFieldName,
            dataSource: $dataSource,
            owner: $fieldOwner,
            matchType: $matchType,
            concatenation: $concatenation,
        );
    }

    /**
     * Build automatically mapping conditions for filters defined in $filters
     *
     * @throws Exception
     */
    protected function buildSearchableFieldFiltering(Builder $queryBuilder, QueryParam $queryParam, DatabaseFilter $filteringField): void
    {
        $value = match ($filteringField->dataSource) {
            DataSource::SEARCH => static::prepareValues($queryParam->searchQuery),
            DataSource::OTHER => static::prepareValues($queryParam->otherParams[$filteringField->sourceFieldName] ?? null),
        };

        //Skip if empty, allow false values
        if ($value === null || $value === '') {
            return;
        }

        $hasOrStatement = $filteringField->concatenation === Concatenation::OR;

        match ($filteringField->owner) {
            Owner::OWN => $this->ownedFieldFilter($queryBuilder, $filteringField->matchType, $filteringField->databaseFieldName, $value, $hasOrStatement),
            Owner::RELATION => $this->relationalFieldFilter($queryBuilder, $filteringField->matchType, $filteringField->databaseFieldName, $value, $hasOrStatement),
        };
    }

    //Add simple flag filter
    protected function addBooleanFilter(Builder $queryBuilder, QueryParam $queryParam, string $column): void
    {
        $conditionFlag = static::preparePhrase($queryParam->otherParams[$column] ?? null);

        $this->when(!is_null($conditionFlag), static function () use ($queryBuilder, $column, $conditionFlag): void {
            $queryBuilder->where($queryBuilder->qualifyColumn($column), (bool) $conditionFlag);
        });
    }

    //Add filter for searching by is_active flag
    protected function addIsActiveFilter(Builder $queryBuilder, QueryParam $queryParam): void
    {
        if (in_array('is_active', $queryBuilder->getModel()->getFillable())) {
            $this->addBooleanFilter($queryBuilder, $queryParam, 'is_active');
        }
    }

    //Add filter for searching by ids as array or single value
    protected function addIdsFilter(Builder $queryBuilder, QueryParam $queryParam): void
    {
        $idsFilter = array_map(
            static fn($item): int => (int) static::preparePhrase($item ?? null),
            (array) ($queryParam->otherParams['ids'] ?? []),
        );

        $this->when($idsFilter !== [], static function () use ($queryBuilder, $idsFilter): void {
            $queryBuilder->whereIn($queryBuilder->qualifyColumn('id'), $idsFilter);
        });
    }

    //Add filter for excluding by ids as array or single value
    protected function addNotIdsFilter(Builder $queryBuilder, QueryParam $queryParam): void
    {
        $idsFilter = array_map(
            static fn($item): int => (int) static::preparePhrase($item ?? null),
            (array) ($queryParam->otherParams['not_ids'] ?? []),
        );

        $this->when($idsFilter !== [], static function () use ($queryBuilder, $idsFilter): void {
            $queryBuilder->whereNotIn($queryBuilder->qualifyColumn('id'), $idsFilter);
        });
    }

    //Filter by integer
    protected function integerFilter(Builder $queryBuilder, QueryParam $queryParam, string $column, string $key): void
    {
        if (!empty($queryParam->otherParams[$key] ?? null)) {
            $queryBuilder->where($column, (int) $queryParam->otherParams[$key]);
        }
    }

    //Clear all values from unnecessary characters
    protected static function prepareValues(null|string|array $value): null|string|array
    {
        if (is_null($value)) {
            return null;
        }

        if (is_array($value)) {
            return array_map(static function (string|array|null $value): array|string|null {
                return static::prepareValues($value);
            }, $value);
        }

        return static::preparePhrase($value);
    }

    //Clear phrase from unnecessary characters
    protected static function preparePhrase(?string $string): ?string
    {
        if (is_null($string)) {
            return null;
        }

        return strtolower(trim($string));
    }

    //Prepare phrase for LIKE condition
    protected function prepareLikeTerm(string $string): ?string
    {
        if ($string === '') {
            return $string;
        }

        return sprintf('%%%s%%', $string);
    }

    protected function prepareBooleanFilterValue(null|string|array $value): ?bool
    {
        if ($value === 'false') {
            return false;
        }

        return (bool) $value;
    }

    protected function when(bool $condition, Closure $callback): void
    {
        if ($condition) {
            $callback();
        }
    }

    //Filter by relational field
    /** @throws Exception */
    private function relationalFieldFilter(Builder $queryBuilder, MatchType $matchType, string $relationalField, string $value, bool $orWhere = true): void
    {
        $method = $orWhere ? 'orWhereHas' : 'whereHas';

        $explodedField = explode('.', $relationalField);

        [$relation, $field] = $explodedField + [null, null];
        $runRecursively = false;

        if (count($explodedField) > 2) {
            $relation = $explodedField[0];

            unset($explodedField[0]);

            $field = implode('.', $explodedField);
            $runRecursively = true;
        }

        $queryBuilder->$method($relation, function (Builder $queryBuilder) use ($matchType, $field, $value, $runRecursively): void {
            if ($runRecursively) {
                $this->relationalFieldFilter($queryBuilder, $matchType, $field, $value, false);
                return;
            }

            $this->ownedFieldFilter($queryBuilder, $matchType, $field, $value, false);
        });
    }

    //Prepare filter for field owned by this table, use prepared match types for basic operations

    /**
     * @throws Exception
     */
    private function ownedFieldFilter(Builder $queryBuilder, MatchType $matchType, string $field, null|string|array $value, bool $orWhere = true): void
    {
        if (is_array($value)) {
            each($value, fn($item) => $this->ownedFieldFilter($queryBuilder, $matchType, $field, $item, $orWhere));
            return;
        }

        $method = $orWhere ? 'orWhere' : 'where';

        $likeValue = $this->prepareLikeTerm($value);
        $qualifiedColumn = $queryBuilder->qualifyColumn($field);

        if (MatchType::matchesOtherThanWhere($matchType)) {
            $this->matchOtherFilteringTypes($queryBuilder, $qualifiedColumn, $orWhere, $value, $matchType);
            return;
        }

        match ($matchType) {
            MatchType::EXACT => $queryBuilder->$method($qualifiedColumn, $value),
            MatchType::LIKE => $queryBuilder->when($likeValue !== '', static function (Builder $query) use ($likeValue, $qualifiedColumn, $method): void {
                $query->$method($qualifiedColumn, 'LIKE', $likeValue);
            }),
            MatchType::BOOLEAN => $queryBuilder->$method($qualifiedColumn, (bool) $this->prepareBooleanFilterValue($value)),
            MatchType::GREATER => $queryBuilder->$method($qualifiedColumn, '>', $value),
            MatchType::GREATER_EQUALS => $queryBuilder->$method($qualifiedColumn, '>=', $value),
            MatchType::LESS => $queryBuilder->$method($qualifiedColumn, '<', $value),
            MatchType::LESS_EQUALS => $queryBuilder->$method($qualifiedColumn, '<=', $value),
            default => Error::ise('Unmatched standard filtering case.'),
        };
    }

    /**
     * @throws Exception
     */
    private function matchOtherFilteringTypes(Builder $queryBuilder, string $qualifiedColumn, bool $matchAsOr, null|string|array $value, MatchType $matchType): void
    {
        match ($matchType) {
            MatchType::NULL, MatchType::NOT_NULL => $this->addNullOperationsFilter($queryBuilder, $qualifiedColumn, $matchAsOr, $value, $matchType),
            default => Error::ise('Unmatched other filtering case.'),

        };
    }

    private function addNullOperationsFilter(Builder $queryBuilder, string $qualifiedColumn, bool $matchAsOr, null|string|array $value, MatchType $matchType): void
    {
        $value = $this->prepareBooleanFilterValue($value);

        if ($matchType === MatchType::NOT_NULL) {
            $value = !$value;
        }

        $nullMethod = $matchAsOr ? 'orWhereNull' : 'whereNull';
        $notNullMethod = $matchAsOr ? 'orWhereNotNull' : 'whereNotNull';

        if ($value) {
            $queryBuilder->$nullMethod($qualifiedColumn);
            return;
        }

        $queryBuilder->$notNullMethod($qualifiedColumn);
    }
}
