<?php

declare(strict_types=1);

namespace Salvon\UPS;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use Salvon\Api\HasGuzzleClient;
use Salvon\Service\Encoder;
use Salvon\UPS\DTO\AddressData;
use Salvon\UPS\DTO\CredentialsData;
use Salvon\UPS\DTO\RatingData;
use Salvon\UPS\DTO\ShipmentData;
use Salvon\UPS\Exception\UPSException;
use Symfony\Component\HttpFoundation\Response;

final class UPS
{
    use HasGuzzleClient;

    /**
     * Create a UPS shipment and return parsed response containing:
     *  - tracking_number(s)
     *  - shipment_identification_number
     *  - labels: array<int, ['format' => string, 'image' => string base64, 'tracking_number' => string]>
     *  - raw: full response body
     *
     * @throws UPSException
     * @throws GuzzleException
     */
    public static function createShipment(ShipmentData $shipment, ?CredentialsData $credentials = null): array
    {
        $credentials = $credentials ?? Config::credentials();

        $packages = [];
        foreach ($shipment->packages as $package) {
            $packages[] = $package->toUPSPayload();
        }

        $shipmentPayload = [
            'Description' => $shipment->description ?? 'Shipment',
            'Shipper' => $shipment->shipper->toUPSPayload(isShipper: true, shipperNumber: $credentials->accountNumber),
            'ShipTo' => $shipment->shipTo->toUPSPayload(),
            'ShipFrom' => $shipment->shipFrom?->toUPSPayload(),
            'PaymentInformation' => [
                'ShipmentCharge' => [
                    'Type' => '01',
                    'BillShipper' => [
                        'AccountNumber' => $shipment->paymentAccountNumber ?? $credentials->accountNumber,
                    ],
                ],
            ],
            'Service' => ['Code' => $shipment->service->value],
            'Package' => $packages,
        ];

        if ($shipment->shipFrom === null) {
            unset($shipmentPayload['ShipFrom']);
        }

        if ($shipment->referenceNumber !== null) {
            $shipmentPayload['ReferenceNumber'] = [
                'Code' => '00',
                'Value' => $shipment->referenceNumber,
            ];
        }

        $body = [
            'ShipmentRequest' => [
                'Request' => ['RequestOption' => 'nonvalidate'],
                'Shipment' => $shipmentPayload,
                'LabelSpecification' => [
                    'LabelImageFormat' => ['Code' => $shipment->labelFormat->value],
                    'HTTPUserAgent' => 'salvon-ups/1.0',
                ],
            ],
        ];

        $response = self::authedJson('post', Url::shipment($credentials->sandbox), $body, $credentials);

        $shipmentResults = $response['ShipmentResponse']['ShipmentResults'] ?? [];
        $packageResults = $shipmentResults['PackageResults'] ?? [];
        if (isset($packageResults['TrackingNumber'])) {
            $packageResults = [$packageResults];
        }

        $labels = [];
        $trackingNumbers = [];
        foreach ($packageResults as $pkg) {
            $tn = (string) ($pkg['TrackingNumber'] ?? '');
            if ($tn !== '') {
                $trackingNumbers[] = $tn;
            }
            $labels[] = [
                'format' => $shipment->labelFormat->value,
                'image' => (string) ($pkg['ShippingLabel']['GraphicImage'] ?? ''),
                'tracking_number' => $tn,
            ];
        }

        return [
            'shipment_identification_number' => (string) ($shipmentResults['ShipmentIdentificationNumber'] ?? ''),
            'tracking_numbers' => $trackingNumbers,
            'labels' => $labels,
            'raw' => $response,
        ];
    }

    /**
     * @throws UPSException
     * @throws GuzzleException
     */
    public static function track(string $trackingNumber, ?CredentialsData $credentials = null, ?string $locale = 'pl_PL'): array
    {
        $credentials = $credentials ?? Config::credentials();
        $client = self::authedClient($credentials, [
            'transId' => uniqid('salvon-ups-', true),
            'transactionSrc' => 'salvon',
        ]);

        $query = ['locale' => $locale, 'returnSignature' => 'false'];

        $response = $client->get(Url::tracking($trackingNumber, $credentials->sandbox), [
            RequestOptions::QUERY => $query,
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() === Response::HTTP_NOT_FOUND) {
            UPSException::throw('ups_tracking_not_found', Response::HTTP_NOT_FOUND);
        }

        if ($response->getStatusCode() >= 400) {
            UPSException::throw('ups_invalid_response', $response->getStatusCode());
        }

        $body = Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);

        $shipment = $body['trackResponse']['shipment'][0] ?? [];
        $pkg = $shipment['package'][0] ?? [];
        $currentStatus = $pkg['currentStatus'] ?? [];
        $activities = $pkg['activity'] ?? [];

        return [
            'tracking_number' => (string) ($pkg['trackingNumber'] ?? $trackingNumber),
            'status_code' => (string) ($currentStatus['code'] ?? ''),
            'status_type' => (string) ($currentStatus['type'] ?? ''),
            'status_description' => (string) ($currentStatus['description'] ?? ''),
            'delivered' => ($currentStatus['type'] ?? '') === 'D',
            'activities' => $activities,
            'raw' => $body,
        ];
    }

    /**
     * @throws UPSException
     * @throws GuzzleException
     */
    public static function rate(RatingData $rating, ?CredentialsData $credentials = null): array
    {
        $credentials = $credentials ?? Config::credentials();

        $packages = [];
        foreach ($rating->packages as $package) {
            $packages[] = $package->toUPSPayload();
        }

        $body = [
            'RateRequest' => [
                'Request' => ['TransactionReference' => ['CustomerContext' => 'salvon-rate']],
                'Shipment' => [
                    'Shipper' => $rating->shipper->toUPSPayload(isShipper: true, shipperNumber: $credentials->accountNumber),
                    'ShipTo' => $rating->shipTo->toUPSPayload(),
                    'ShipFrom' => $rating->shipFrom?->toUPSPayload(),
                    'Service' => ['Code' => $rating->service->value],
                    'Package' => $packages,
                ],
            ],
        ];

        if ($rating->shipFrom === null) {
            unset($body['RateRequest']['Shipment']['ShipFrom']);
        }

        $response = self::authedJson('post', Url::rate($credentials->sandbox), $body, $credentials);

        $rateResponse = $response['RateResponse']['RatedShipment'] ?? [];
        $totalCharges = $rateResponse['TotalCharges'] ?? [];

        return [
            'currency' => (string) ($totalCharges['CurrencyCode'] ?? ''),
            'amount' => (float) ($totalCharges['MonetaryValue'] ?? 0),
            'service_code' => $rating->service->value,
            'raw' => $response,
        ];
    }

    /**
     * Address validation (XAV). Returns ['valid' => bool, 'candidates' => array, 'raw' => array].
     *
     * @throws UPSException
     * @throws GuzzleException
     */
    public static function validateAddress(AddressData $address, ?CredentialsData $credentials = null): array
    {
        $credentials = $credentials ?? Config::credentials();

        $body = [
            'XAVRequest' => [
                'AddressKeyFormat' => [
                    'AddressLine' => $address->addressLines,
                    'PoliticalDivision2' => $address->city,
                    'PostcodePrimaryLow' => $address->postalCode,
                    'CountryCode' => $address->countryCode,
                ],
            ],
        ];

        if ($address->stateProvinceCode !== null) {
            $body['XAVRequest']['AddressKeyFormat']['PoliticalDivision1'] = $address->stateProvinceCode;
        }

        $response = self::authedJson('post', Url::addressValidation($credentials->sandbox), $body, $credentials);

        $xav = $response['XAVResponse'] ?? [];
        $valid = isset($xav['ValidAddressIndicator']);
        $candidates = $xav['Candidate'] ?? [];
        if (!empty($candidates) && isset($candidates['AddressKeyFormat'])) {
            $candidates = [$candidates];
        }

        return [
            'valid' => $valid,
            'ambiguous' => isset($xav['AmbiguousAddressIndicator']),
            'no_candidates' => isset($xav['NoCandidatesIndicator']),
            'candidates' => $candidates,
            'raw' => $response,
        ];
    }

    /**
     * @throws UPSException
     * @throws GuzzleException
     */
    public static function voidShipment(string $shipmentIdentificationNumber, ?CredentialsData $credentials = null): array
    {
        $credentials = $credentials ?? Config::credentials();
        $client = self::authedClient($credentials);

        $response = $client->delete(Url::voidShipment($shipmentIdentificationNumber, $credentials->sandbox), [
            'http_errors' => false,
        ]);

        if ($response->getStatusCode() >= 400) {
            UPSException::throw('ups_void_failed', $response->getStatusCode());
        }

        return Encoder::arrayFromJson(value: $response->getBody()->getContents(), flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @throws UPSException
     * @throws GuzzleException
     */
    private static function authedJson(string $method, string $url, array $body, CredentialsData $credentials): array
    {
        $client = self::authedClient($credentials);

        $response = $client->{$method}($url, [
            RequestOptions::JSON => $body,
            'http_errors' => false,
        ]);

        $bodyContents = $response->getBody()->getContents();

        if ($response->getStatusCode() >= 400) {
            $decoded = json_decode($bodyContents, true) ?? [];
            $firstError = $decoded['response']['errors'][0] ?? null;
            $message = is_array($firstError)
                ? sprintf('ups_api_error:%s:%s', $firstError['code'] ?? '0', $firstError['message'] ?? '')
                : 'ups_invalid_response';

            UPSException::throw($message, $response->getStatusCode());
        }

        return Encoder::arrayFromJson(value: $bodyContents, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @throws UPSException
     * @throws GuzzleException
     */
    private static function authedClient(CredentialsData $credentials, array $extraHeaders = []): GuzzleClient
    {
        $token = Auth::accessToken($credentials);

        return self::guzzleClient([
            'headers' => array_merge([
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], $extraHeaders),
        ]);
    }
}
