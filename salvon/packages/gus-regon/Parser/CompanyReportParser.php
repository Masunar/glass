<?php

declare(strict_types=1);

namespace Salvon\Regon\Parser;

use Exception;
use Salvon\Regon\Definition\LegalForm;
use Salvon\Regon\DTO\CompanyReport\Person;
use Salvon\Regon\DTO\CompanyReport\Address;
use Salvon\Regon\Parser\Trait\HandleResult;
use Salvon\Regon\DTO\CompanyReport\Organization;
use Salvon\Regon\DTO\CompanyReport\CompanyReport;

final class CompanyReportParser
{
    use HandleResult;

    /**
     * @throws Exception
     * @param list<array<string, string|null>> $reports
     */
    public static function execute(LegalForm $legalForm, array $reports): CompanyReport
    {
        $report = array_merge(...$reports);

        $parsedReport = [];
        foreach ($report as $key => $value) {
            $parsedReport[self::prepareKey($key)] = $value;
        }

        return self::createDataObject($legalForm, $parsedReport);
    }

    private static function prepareKey(string $key): string
    {
        return lcfirst(
            str_replace(['fiz_', 'praw_', 'fizC_'], ['', '', ''], $key),
        );
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function value(array $data, string $key): ?string
    {
        return $data[$key] ?? null;
    }

    /**
     * @param array<string, string|null> $data
     * @param string[] $keys
     */
    private static function oneOf(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $result = $data[$key] ?? null;

            if(!is_null($result)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function createDataObject(LegalForm $legalForm, array $data): CompanyReport
    {
        return new CompanyReport(
            regon9: self::value($data, 'regon9'),
            nip: self::value($data, 'nip'),
            nipStatus: self::value($data, 'statusNip'),
            dateOfChangeOccurrence: self::value($data, 'dataZaistnieniaZmiany'),
            basicLegalFormSymbol: self::value($data, 'podstawowaFormaPrawna_Symbol'),
            specialLegalFormSymbol: self::value($data, 'szczegolnaFormaPrawna_Symbol'),
            financingFormSymbol: self::value($data, 'formaFinansowania_Symbol'),
            ownershipFormSymbol: self::value($data, 'formaWlasnosci_Symbol'),
            basicLegalFormName: self::value($data, 'podstawowaFormaPrawna_Nazwa'),
            specialLegalFormName: self::value($data, 'szczegolnaFormaPrawna_Nazwa'),
            financingFormName: self::value($data, 'formaFinansowania_Nazwa'),
            ownershipFormName: self::value($data, 'formaWlasnosci_Nazwa'),
            localUnitCount: self::value($data, 'liczbaJednLokalnych'),
            name: self::value($data, 'nazwa'),
            shortName: self::value($data, 'nazwaSkrocona'),
            creationDate: self::value($data, 'dataPowstania'),
            activityStartDate: self::value($data, 'dataRozpoczeciaDzialalnosci'),
            activitySuspendedDate: self::value($data, 'dataZawieszeniaDzialalnosci'),
            activityReactivatedDate: self::value($data, 'dataWznowieniaDzialalnosci'),
            activityTerminatedDate: self::value($data, 'dataZakonczeniaDzialalnosci'),
            bankruptcyDate: self::value($data, 'dataOrzeczeniaOUpadlosci'),
            dateOfBankruptcyProceeding: self::value($data, 'dataZakonczeniaPostepowaniaUpadlosciowego'),
            phone: self::value($data, 'numerTelefonu'),
            phoneInternal: self::value($data, 'numerWewnetrznyTelefonu'),
            fax: self::value($data, 'numerFaksu'),
            email: self::value($data, 'adresEmail'),
            websiteUrl: self::value($data, 'adresStronyinternetowej'),
            dateOfEntryToRegistryOfRecords: self::value($data, 'dataWpisuDoRejestruEwidencji'),
            registryOfRecordsNumber: self::value($data, 'numerWRejestrzeEwidencji'),
            registrationAuthoritySymbol: self::value($data, 'organRejestrowy_Symbol'),
            registrationAuthorityName: self::value($data, 'organRejestrowy_Nazwa'),

            //Shared fields but different keys
            regonEntryDate: self::oneOf($data, ['dataWpisuPodmiotuDoRegon', 'dataWpisuDoRegon']),
            regonDeletionDate: self::oneOf($data, ['dataSkresleniaPodmiotuZRegon', 'dataSkresleniaZRegon']),
            registryTypeSymbol: self::oneOf($data, ['rodzajRejestru_Symbol', 'rodzajRejestruEwidencji_Symbol']),
            registryTypeName: self::oneOf($data, ['rodzajRejestru_Nazwa', 'rodzajRejestruEwidencji_Nazwa']),
            address: self::addressData($data),
            person: self::personData($legalForm, $data),
            organization: self::organizationData($legalForm, $data),
        );
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function addressData(array $data): Address
    {
        return new Address(
            countrySymbol: self::value($data, 'adSiedzKraj_Symbol'),
            provinceSymbol: self::value($data, 'adSiedzWojewodztwo_Symbol'),
            districtSymbol: self::value($data, 'adSiedzPowiat_Symbol'),
            communeSymbol: self::value($data, 'adSiedzGmina_Symbol'),
            postcode: self::value($data, 'adSiedzKodPocztowy'),
            postcodeCitySymbol: self::value($data, 'adSiedzMiejscowoscPoczty_Symbol'),
            streetSymbol: self::value($data, 'adSiedzUlica_Symbol'),
            buildingNumber: self::value($data, 'adSiedzNumerNieruchomosci'),
            apartmentNumber: self::value($data, 'adSiedzNumerLokalu'),
            unusualPlaceLocation: self::value($data, 'adSiedzNietypoweMiejsceLokalizacji'),
            countryName: self::value($data, 'adSiedzKraj_Nazwa'),
            provinceName: self::value($data, 'adSiedzWojewodztwo_Nazwa'),
            districtName: self::value($data, 'adSiedzPowiat_Nazwa'),
            communeName: self::value($data, 'adSiedzGmina_Nazwa'),
            cityName: self::value($data, 'adSiedzMiejscowosc_Nazwa'),
            postcodeCityName: self::value($data, 'adSiedzMiejscowoscPoczty_Nazwa'),
            streetName: self::value($data, 'adSiedzUlica_Nazwa'),
        );
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function personData(LegalForm $legalForm, array $data): ?Person
    {
        if($legalForm !== LegalForm::PERSON) {
            return null;
        }

        return new Person(
            lastName: self::value($data, 'nazwisko'),
            firstName: self::value($data, 'imie1'),
            secondName: self::value($data, 'imie2'),
            ceidgActivity: self::value($data, 'dzialalnoscCeidg'),
            agriculturalActivity: self::value($data, 'dzialalnoscRolnicza'),
            otherActivity: self::value($data, 'dzialalnoscPozostala'),
            removedBefore20141108: self::value($data, 'dzialalnoscSkreslonaDo20141108'),
            activityRegonEntryDate: self::value($data, 'dataWpisuDzialalnosciDoRegon'),
            activityChangeRegonDate: self::value($data, 'dataZaistnieniaZmianyDzialalnosci'),
            activityRegonDeletionDate: self::value($data, 'dataSkresleniaDzialalnosciZRegon'),
            activityRegonRegistryRemoveDate: self::value($data, 'dataSkresleniaZRejestruEwidencji'),
            activityNotStarted: self::value($data, 'niePodjetoDzialalnosci'),
        );
    }

    /**
     * @param array<string, string|null> $data
     */
    private static function organizationData(LegalForm $legalForm, array $data): ?Organization
    {
        if($legalForm !== LegalForm::ORGANIZATION) {
            return null;
        }

        return new Organization(
            foundingAuthoritySymbol: self::value($data, 'organZalozycielski_Symbol'),
            foundingAuthorityName: self::value($data, 'organZalozycielski_Nazwa'),
        );
    }
}
