<?php

namespace App\Services;

use App\Models\Pesanan;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MidtransService
{
    private string $serverKey;
    private bool $isProduction;

    public function __construct()
    {
        $this->serverKey = (string) config('services.midtrans.server_key');
        $this->isProduction = (bool) config('services.midtrans.is_production', false);
    }

    private function snapBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://app.midtrans.com/snap/v1'
            : 'https://app.sandbox.midtrans.com/snap/v1';
    }

    private function apiBaseUrl(): string
    {
        return $this->isProduction
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->serverKey, '')
            ->acceptJson()
            ->withOptions([
                'verify' => app()->isProduction(),
            ]);
    }

    /**
     * Create Snap transaction and return ['order_id', 'token', 'redirect_url'].
     */
    public function createSnapTransaction(Pesanan $pesanan): array
    {
        if ($this->serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum dikonfigurasi.');
        }

        $pesanan->loadMissing(['user', 'detailPesanans.produk', 'ekspedisi']);

        $gatewayOrderId = 'EM-' . $pesanan->id_pesanan . '-' . time();

        $itemDetails = [];
        foreach ($pesanan->detailPesanans as $detail) {
            $itemDetails[] = [
                'id' => 'PROD-' . $detail->id_produk,
                'price' => (int) $detail->harga_beli,
                'quantity' => (int) $detail->qty,
                'name' => mb_substr($detail->produk->nama_produk ?? 'Produk Elektronik', 0, 50),
            ];
        }

        if ((int) $pesanan->ongkos_kirim > 0) {
            $itemDetails[] = [
                'id' => 'SHIPPING',
                'price' => (int) $pesanan->ongkos_kirim,
                'quantity' => 1,
                'name' => mb_substr('Ongkir - ' . ($pesanan->ekspedisi->nama_ekspedisi ?? 'Kurir'), 0, 50),
            ];
        }

        if ((int) $pesanan->diskon > 0) {
            $itemDetails[] = [
                'id' => 'DISCOUNT',
                'price' => -1 * (int) $pesanan->diskon,
                'quantity' => 1,
                'name' => 'Diskon Voucher',
            ];
        }

        $payload = [
            'transaction_details' => [
                'order_id' => $gatewayOrderId,
                'gross_amount' => (int) $pesanan->total_bayar,
            ],
            'item_details' => $itemDetails,
            'customer_details' => [
                'first_name' => $pesanan->user->nama ?? 'Customer',
                'email' => $pesanan->user->email ?? 'customer@example.com',
            ],
            'callbacks' => [
                'finish' => route('payments.midtrans.finish'),
            ],
        ];

        $response = $this->client()
            ->post($this->snapBaseUrl() . '/transactions', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Midtrans Snap gagal: ' . $response->body());
        }

        $data = $response->json();

        return [
            'order_id' => $gatewayOrderId,
            'token' => $data['token'] ?? null,
            'redirect_url' => $data['redirect_url'] ?? null,
        ];
    }

    /**
     * Fetch live transaction status from Midtrans API.
     */
    public function getTransactionStatus(string $gatewayOrderId): array
    {
        $response = $this->client()
            ->get($this->apiBaseUrl() . '/' . urlencode($gatewayOrderId) . '/status');

        if (!$response->successful()) {
            throw new RuntimeException('Gagal memeriksa status Midtrans: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Verify SHA-512 signature from Midtrans webhook notification.
     */
    public function isValidSignature(array $payload): bool
    {
        $orderId = (string) ($payload['order_id'] ?? '');
        $statusCode = (string) ($payload['status_code'] ?? '');
        $grossAmount = (string) ($payload['gross_amount'] ?? '');
        $signatureKey = (string) ($payload['signature_key'] ?? '');

        if ($orderId === '' || $statusCode === '' || $grossAmount === '' || $signatureKey === '') {
            return false;
        }

        $expected = hash('sha512', $orderId . $statusCode . $grossAmount . $this->serverKey);

        return hash_equals($expected, $signatureKey);
    }
}