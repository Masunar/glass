<?php

namespace Salvon\Regon\Definition;

enum Report: string
{
    public const array ARRAYABLE_REPORTS = [
        self::PERSON_PKD,
        self::PERSON_LOCAL_PKD,
        self::ORGANIZATION_PKD,
        self::ORGANIZATION_LOCAL_PKD,
    ];

    public static function isArrayable(self $report): bool
    {
        return in_array($report, self::ARRAYABLE_REPORTS);
    }

    public static function pkdReportFromLegalForm(LegalForm $legalForm): self
    {
        return match ($legalForm) {
            LegalForm::PERSON => self::PERSON_PKD,
            LegalForm::ORGANIZATION => self::ORGANIZATION_PKD,
            LegalForm::LOCAL_PERSON => self::PERSON_LOCAL_PKD,
        };
    }

    /**
     * @return self[]
     */
    public static function companyReportFromLegalForm(LegalForm $legalForm): array
    {
        return match ($legalForm) {
            LegalForm::PERSON => [self::PERSON, self::PERSON_CEIDG],
            LegalForm::ORGANIZATION => [self::ORGANIZATION],
            LegalForm::LOCAL_PERSON => [],
        };
    }

    case PERSON = 'BIR12OsFizycznaDaneOgolne';
    case PERSON_CEIDG = 'BIR12OsFizycznaDzialalnoscCeidg';
    case PERSON_AGRO = 'BIR12OsFizycznaDzialalnoscRolnicza';
    case PERSON_OTHER = 'BIR12OsFizycznaDzialalnoscPozostala';
    case PERSON_LOCALS = 'BIR12OsFizycznaListaJednLokalnych';
    case PERSON_LOCAL = 'BIR12JednLokalnaOsFizycznej';
    case PERSON_PKD = 'BIR12OsFizycznaPkd';
    case PERSON_LOCAL_PKD = 'BIR12JednLokalnaOsFizycznejPkd';
    case ORGANIZATION = 'BIR12OsPrawna';
    case ORGANIZATION_PKD = 'BIR12OsPrawnaPkd';
    case ORGANIZATION_LOCALS = 'BIR12OsPrawnaListaJednLokalnych';
    case ORGANIZATION_LOCAL = 'BIR12JednLokalnaOsPrawnej';
    case ORGANIZATION_LOCAL_PKD = 'BIR12JednLokalnaOsPrawnejPkd';
    case ORGANIZATION_PARTNERS = 'BIR12OsPrawnaSpCywilnaWspolnicy';
    case UNIT_TYPE = 'BIR12TypPodmiotu';
}
