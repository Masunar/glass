<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Request;
use Salvon\Regon\Regon;
use Salvon\Regon\DTO\SearchResult;

final readonly class RegonService
{
    public function findByNip(Request $request): array
    {
        crud_validate($request, [
            'nip' => 'required|string',
        ]);

        $nip = $request->post('nip');
        $token = config('salvon.regon.token');
        $useTestEnv = config('salvon.regon.use_test_env');

        try {
            $data = Regon::searchClient($token, $useTestEnv)->byNip($nip);
        } catch (Exception) {
            return [];
        }

        if (!$data instanceof SearchResult) {
            return ['nip' => $nip];
        }

        return [
            'nip'              => $data->nip,
            'regon'            => $data->regon,
            'company_name'     => $data->name,
            'street'           => $data->street,
            'building_number'  => $data->propertyNumber,
            'apartment_number' => $data->apartmentNumber,
            'postcode'         => $data->postcode,
            'city'             => $data->city,
            'commune'          => $data->commune,
            'postal_city'      => $data->postcodeCity,
            'district'         => $data->district,
            'province'         => $data->province,
        ];
    }
}
