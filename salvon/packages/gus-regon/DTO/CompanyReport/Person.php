<?php

declare(strict_types=1);

namespace Salvon\Regon\DTO\CompanyReport;

final readonly class Person
{
    public function __construct(
        public ?string $lastName = null, //nazwisko
        public ?string $firstName = null, //imie1
        public ?string $secondName = null, //imie2
        public ?string $ceidgActivity = null, //dzialalnoscCeidg
        public ?string $agriculturalActivity = null, //dzialalnoscRolnicza
        public ?string $otherActivity = null, //dzialalnoscPozostala
        public ?string $removedBefore20141108 = null, //dzialalnoscSkreslonaDo20141108
        public ?string $activityRegonEntryDate = null, //dataWpisuDzialalnosciDoRegon
        public ?string $activityChangeRegonDate = null, //dataZaistnieniaZmianyDzialalnosci
        public ?string $activityRegonDeletionDate = null, //dataSkresleniaDzialalnosciZRegon
        public ?string $activityRegonRegistryRemoveDate = null, //dataSkresleniaZRejestruEwidencji
        public ?string $activityNotStarted = null, //niePodjetoDzialalnosci
    ) {}
}
