<?php

declare(strict_types=1);

namespace Salvon\Regon\Client;

use DateTime;
use DateTimeInterface;
use Exception;
use SoapFault;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\Definition\BulkReport;

final readonly class BulkReportClient extends RegonClient
{
    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function newCompanies(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::NEW_COMPANIES, $dateTime);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function updatedCompanies(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::UPDATED_COMPANIES, $dateTime);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function deletedCompanies(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::DELETED_COMPANIES, $dateTime);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function newLocalUnits(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::NEW_LOCAL_UNITS, $dateTime);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function updatedLocalUnits(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::UPDATED_LOCAL_UNITS, $dateTime);
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @return Collection<string>|null
     */
    public function deletedLocalUnits(DateTimeInterface $dateTime = new DateTime()): ?Collection
    {
        return $this->bulkReport(BulkReport::DELETED_LOCAL_UNITS, $dateTime);
    }
}
