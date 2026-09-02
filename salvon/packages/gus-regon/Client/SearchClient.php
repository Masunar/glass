<?php

declare(strict_types=1);

namespace Salvon\Regon\Client;

use Exception;
use SoapFault;
use Salvon\Regon\Data\Collection;
use Salvon\Regon\DTO\SearchResult;
use Salvon\Regon\Helper\DataClearer;
use Salvon\Regon\Definition\SearchType;

final readonly class SearchClient extends RegonClient
{
    /**
     * @throws SoapFault
     * @throws Exception
     */
    public function byNip(string $nip): ?SearchResult
    {
        $nip = DataClearer::nip($nip);

        return $this->searchBy(SearchType::NIP, $nip)?->first();
    }

    /**
     * @throws SoapFault
     * @throws Exception
     * @param string[] $nips
     * @return Collection<SearchResult>|null
     */
    public function byNips(array $nips): ?Collection
    {
        $nips = DataClearer::nips($nips);

        return $this->searchBy(SearchType::NIPS, $nips);
    }

    /**
     * @throws SoapFault
     */
    public function byRegon(string $regon): ?SearchResult
    {
        return $this->searchBy(SearchType::REGON, $regon)?->first();
    }

    /**
     * @throws SoapFault
     */
    public function byKrs(string $krs): ?SearchResult
    {
        return $this->searchBy(SearchType::KRS, $krs)?->first();
    }
}
