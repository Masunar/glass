<?php

declare(strict_types=1);

namespace Salvon\Enum\Eloquent\Searching;

enum MatchType: string
{
    private const array OTHER_THAN_WHERE_METHODS = [
        self::NULL, self::NOT_NULL,
    ];

    public static function matchesOtherThanWhere(self $matchType): bool
    {
        return in_array($matchType, self::OTHER_THAN_WHERE_METHODS);
    }

    case EXACT = 'exact';
    case LIKE = 'like';
    case BOOLEAN = 'boolean';
    case GREATER = 'gt';
    case GREATER_EQUALS = 'gte';
    case LESS = 'lt';
    case LESS_EQUALS = 'lte';
    case NULL = 'null';
    case NOT_NULL = 'not_null';
}
