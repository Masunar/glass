<?php

declare(strict_types=1);

namespace Salvon\Regon\Definition;

use Exception;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\Exception\UnsupportedLegalForm;

enum LegalForm: string
{
    /**
     * @throws Exception
     */
    public static function fromSearchResult(SearchResult $searchResult): LegalForm
    {
        $legalForm = self::tryFromSearchResult($searchResult);

        if (is_null($legalForm)) {
            throw new UnsupportedLegalForm(sprintf('Legal form "%s" is not supported', $searchResult->type));
        }

        return $legalForm;
    }

    public static function tryFromSearchResult(SearchResult $searchResult): ?LegalForm
    {
        return LegalForm::tryFrom(strtoupper($searchResult->type ?? ''));
    }

    case ORGANIZATION = 'P';
    case PERSON = 'F';
    case LOCAL_PERSON = 'LF';
}
