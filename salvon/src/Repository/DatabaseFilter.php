<?php

declare(strict_types=1);

namespace Salvon\Repository;

use Salvon\Enum\Eloquent\Searching\Owner;
use Salvon\Enum\Eloquent\Searching\MatchType;
use Salvon\Enum\Eloquent\Searching\DataSource;
use Salvon\Enum\Eloquent\Searching\Concatenation;

readonly class DatabaseFilter
{
    public function __construct(
        public string        $sourceFieldName,
        public string        $databaseFieldName,
        public DataSource    $dataSource,
        public Owner         $owner,
        public MatchType     $matchType,
        public Concatenation $concatenation,
    ) {}
}
