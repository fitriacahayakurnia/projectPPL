<?php

namespace App\Services;

use App\Models\AlamatUser;
use App\Models\Ekspedisi;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class RajaOngkirService
{
    private string $apiKey;
    private string $originSearch;
    private string $baseUrl;

    private const COURIER_MAP = [
        'jne' => 'JNE',
        'jnt' => 'JNT',
        'sicepat' => 'SiCepat',
        'pos' => 'POS',
        'tiki' => 'TIKI',
    ];

    public function __construct()
    {
        $this->apiKey = (string) config('services.rajaongkir.api_key');
        $this->originSearch = (string) config('services.rajaongkir.origin_search', 'Surabaya');
        $this->baseUrl = rtrim((string) config('services.rajaongkir.base_url', 'https://rajaongkir.komerce.id/api/v1'), '/');
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'key' => $this->apiKey,
            'Accept' => 'application/json',
        ])->withOptions([
            'verify' => app()->isProduction(),
            'timeout' => 12,
        ]);
    }

    /**
     * Resolve domestic destination ID by keyword (cached for 7 days).
     */
    public function resolveDestinationId(string $keyword): ?int
    {
        $clean = trim(preg_replace('/\s+/', ' ', $keyword));
        if ($clean === '' || $this->apiKey === '') {
            return null;
        }

        $cacheKey = 'rajaongkir:dest:' . md5(mb_strtolower($clean));

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($clean) {
            $response = $this->client()->get($this->baseUrl . '/destination/domestic-destination', [
                'search' => $clean,
                'limit' => 5,
                'offset' => 0,
            ]);

            if (!$response->successful()) {
                Log::warning('RajaOngkir destination lookup failed', [
                    'keyword' => $clean,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $items = $response->json('data') ?? [];
            if (!is_array($items) || empty($items)) {
                return null;
            }

            return isset($items[0]['id']) ? (int) $items[0]['id'] : null;
        });
    }

    /**
     * Resolve destination ID from an AlamatUser record using progressive fallback.
     */
    public function resolveAddressDestinationId(AlamatUser $alamat): ?int
    {
        $alamat->loadMissing(['desa.kecamatan.kota.provinsi']);

        $desa = $alamat->desa?->nama_desa ?? '';
        $kecamatan = $alamat->desa?->kecamatan?->nama_kecamatan ?? '';
        $kota = $alamat->desa?->kecamatan?->kota?->nama_kota ?? '';
        $cleanKota = trim(preg_replace('/^(KABUPATEN|KAB\.|KOTA)\s+/i', '', $kota));

        $candidates = array_filter([
            trim("{$kecamatan} {$cleanKota}"),
            trim("{$desa} {$cleanKota}"),
            $cleanKota,
            $kota,
        ]);

        foreach (array_unique($candidates) as $query) {
            $id = $this->resolveDestinationId($query);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    /**
     * Calculate domestic shipping rates for all available Ekspedisi couriers.
     *
     * @return array{
     *   source: 'api'|'fallback',
     *   origin: string,
     *   destination: string,
     *   weight_grams: int,
     *   rates: array<int, array<string, mixed>>
     * }
     */
    public function calculateRates(AlamatUser $alamat, int $totalWeightGrams, Collection $ekspedisis): array
    {
        $weight = max(1000, $totalWeightGrams);
        $alamat->loadMissing(['desa.kecamatan.kota.provinsi']);

        $destinationLabel = trim(implode(', ', array_filter([
            $alamat->desa?->kecamatan?->nama_kecamatan,
            $alamat->desa?->kecamatan?->kota?->nama_kota,
            $alamat->desa?->kecamatan?->kota?->provinsi?->nama_provinsi,
        ])));

        if ($this->apiKey === '') {
            return $this->buildFallbackResponse($ekspedisis, $weight, $destinationLabel, 'API key belum dikonfigurasi');
        }

        try {
            $originId = $this->resolveDestinationId($this->originSearch);
            $destinationId = $this->resolveAddressDestinationId($alamat);

            if (!$originId || !$destinationId) {
                return $this->buildFallbackResponse($ekspedisis, $weight, $destinationLabel, 'Lokasi tujuan tidak ditemukan di RajaOngkir');
            }

            $ekspedisiByLowerName = $ekspedisis->keyBy(fn ($e) => mb_strtolower(trim($e->nama_ekspedisi)));
            $courierCodes = [];
            foreach (self::COURIER_MAP as $code => $dbName) {
                if ($ekspedisiByLowerName->has(mb_strtolower($dbName))) {
                    $courierCodes[] = $code;
                }
            }

            if (empty($courierCodes)) {
                $courierCodes = ['jne', 'jnt', 'sicepat', 'pos', 'tiki'];
            }

            $cacheKey = sprintf('rajaongkir:cost:%d:%d:%d:%s', $originId, $destinationId, $weight, implode('-', $courierCodes));

            $apiServices = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($originId, $destinationId, $weight, $courierCodes) {
                $response = $this->client()
                    ->asForm()
                    ->post($this->baseUrl . '/calculate/domestic-cost', [
                        'origin' => $originId,
                        'destination' => $destinationId,
                        'weight' => $weight,
                        'courier' => implode(':', $courierCodes),
                        'price' => 'lowest',
                    ]);

                if (!$response->successful()) {
                    Log::warning('RajaOngkir cost calculation failed', [
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                    return null;
                }

                return $response->json('data');
            });

            if (!is_array($apiServices) || empty($apiServices)) {
                return $this->buildFallbackResponse($ekspedisis, $weight, $destinationLabel, 'Tarif API kosong');
            }

            $rates = [];
            foreach ($apiServices as $item) {
                $code = mb_strtolower((string) ($item['code'] ?? ''));
                $mappedName = self::COURIER_MAP[$code] ?? strtoupper($code);
                $ekspedisi = $ekspedisiByLowerName->get(mb_strtolower($mappedName));

                if (!$ekspedisi) {
                    continue;
                }

                $service = (string) ($item['service'] ?? 'REG');
                $description = (string) ($item['description'] ?? '');
                $cost = (int) ($item['cost'] ?? $ekspedisi->biaya_pengiriman);
                $etd = trim((string) ($item['etd'] ?? ''));
                if ($etd !== '' && !str_contains(mb_strtolower($etd), 'hari')) {
                    $etd .= ' hari';
                }

                $rates[] = [
                    'rate_key' => $ekspedisi->id_ekspedisi . ':' . $service,
                    'id_ekspedisi' => $ekspedisi->id_ekspedisi,
                    'courier_code' => $code,
                    'courier_name' => $ekspedisi->nama_ekspedisi,
                    'service' => $service,
                    'description' => $description,
                    'cost' => $cost,
                    'etd' => $etd !== '' ? $etd : '2-4 hari',
                ];
            }

            if (empty($rates)) {
                return $this->buildFallbackResponse($ekspedisis, $weight, $destinationLabel, 'Kurir tidak cocok');
            }

            return [
                'source' => 'api',
                'origin' => $this->originSearch,
                'destination' => $destinationLabel,
                'weight_grams' => $weight,
                'rates' => $rates,
            ];
        } catch (Throwable $e) {
            Log::error('RajaOngkir exception: ' . $e->getMessage());
            return $this->buildFallbackResponse($ekspedisis, $weight, $destinationLabel, $e->getMessage());
        }
    }

    private function buildFallbackResponse(Collection $ekspedisis, int $weight, string $destinationLabel, string $reason): array
    {
        $rates = [];
        foreach ($ekspedisis as $ekspedisi) {
            $rates[] = [
                'rate_key' => $ekspedisi->id_ekspedisi . ':REG',
                'id_ekspedisi' => $ekspedisi->id_ekspedisi,
                'courier_code' => mb_strtolower($ekspedisi->nama_ekspedisi),
                'courier_name' => $ekspedisi->nama_ekspedisi,
                'service' => 'REG',
                'description' => 'Tarif Standar (Fallback)',
                'cost' => (int) $ekspedisi->biaya_pengiriman,
                'etd' => '2-4 hari',
            ];
        }

        return [
            'source' => 'fallback',
            'reason' => $reason,
            'origin' => $this->originSearch,
            'destination' => $destinationLabel,
            'weight_grams' => $weight,
            'rates' => $rates,
        ];
    }
}