<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO\CompanyReport;

final readonly class CompanyReport
{
    public function __construct(
        public ?string $regon9 = null, //regon9
        public ?string $nip = null, //nip
        public ?string $nipStatus = null, //statusNip
        public ?string $dateOfChangeOccurrence = null, //dataZaistnieniaZmiany
        public ?string $basicLegalFormSymbol = null, //podstawowaFormaPrawna_Symbol
        public ?string $specialLegalFormSymbol = null, //szczegolnaFormaPrawna_Symbol
        public ?string $financingFormSymbol = null, //formaFinansowania_Symbol
        public ?string $ownershipFormSymbol = null, //formaWlasnosci_Symbol
        public ?string $basicLegalFormName = null, //podstawowaFormaPrawna_Nazwa
        public ?string $specialLegalFormName = null, //szczegolnaFormaPrawna_Nazwa
        public ?string $financingFormName = null, //formaFinansowania_Nazwa
        public ?string $ownershipFormName = null, //formaWlasnosci_Nazwa
        public ?string $localUnitCount = null, //liczbaJednLokalnych
        public ?string $name = null, //nazwa
        public ?string $shortName = null, //nazwaSkrocona
        public ?string $creationDate = null, //dataPowstania
        public ?string $activityStartDate = null, //dataRozpoczeciaDzialalnosci
        public ?string $activitySuspendedDate = null, //dataZawieszeniaDzialalnosci
        public ?string $activityReactivatedDate = null, //dataWznowieniaDzialalnosci
        public ?string $activityTerminatedDate = null, //dataZakonczeniaDzialalnosci
        public ?string $bankruptcyDate = null, //dataOrzeczeniaOUpadlosci
        public ?string $dateOfBankruptcyProceeding = null, //dataZakonczeniaPostepowaniaUpadlosciowego
        public ?string $phone = null, //numerTelefonu
        public ?string $phoneInternal = null, //numerWewnetrznyTelefonu
        public ?string $fax = null, //numerFaksu
        public ?string $email = null, //adresEmail
        public ?string $websiteUrl = null, //adresStronyinternetowej
        public ?string $dateOfEntryToRegistryOfRecords = null, //dataWpisuDoRejestruEwidencji
        public ?string $registryOfRecordsNumber = null, //numerWRejestrzeEwidencji
        public ?string $registrationAuthoritySymbol = null, //organRejestrowy_Symbol
        public ?string $registrationAuthorityName = null, //organRejestrowy_Nazwa

        public ?string $regonEntryDate = null, //dataWpisuPodmiotuDoRegon, dataWpisuDoRegon
        public ?string $regonDeletionDate = null, //dataSkresleniaPodmiotuZRegon, dataSkresleniaZRegon
        public ?string $registryTypeSymbol = null, //rodzajRejestru_Symbol, rodzajRejestruEwidencji_Symbol
        public ?string $registryTypeName = null, //rodzajRejestru_Nazwa, rodzajRejestruEwidencji_Nazwa

        public ?string $organizationFoundingAuthoritySymbol = null, //organZalozycielski_Symbol
        public ?string $organizationFoundingAuthorityName = null, //organZalozycielski_Nazwa

        public ?Address $address = null,
        public ?Person $person = null,
        public ?Organization $organization = null,
    ) {}
}
