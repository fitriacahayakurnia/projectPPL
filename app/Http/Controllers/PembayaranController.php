<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi;
use App\Models\Pembayaran;
use App\Models\Pesanan;
use App\Models\Produk;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class PembayaranController extends Controller
{
    public function __construct(private MidtransService $midtrans)
    {
    }

    /**
     * Server-to-server webhook callback from Midtrans.
     */
    public function notification(Request $request)
    {
        $payload = $request->all();

        if (!$this->midtrans->isValidSignature($payload)) {
            Log::warning('Midtrans invalid signature', ['order_id' => $payload['order_id'] ?? null]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $pembayaran = Pembayaran::where('gateway_order_id', $payload['order_id'] ?? '')
            ->with('pesanan.detailPesanans')
            ->first();

        if (!$pembayaran || !$pembayaran->pesanan) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ((int) round((float) ($payload['gross_amount'] ?? 0)) !== (int) $pembayaran->pesanan->total_bayar) {
            return response()->json(['message' => 'Amount mismatch'], 422);
        }

        $this->applyGatewayStatus($pembayaran, $payload);

        return response()->json(['message' => 'OK']);
    }

    /**
     * Customer redirect URL after finishing/closing Midtrans Snap.
     */
    public function finish(Request $request)
    {
        $orderId = (string) $request->query('order_id', '');
        $pembayaran = Pembayaran::where('gateway_order_id', $orderId)
            ->with('pesanan.detailPesanans')
            ->first();

        if ($pembayaran && $pembayaran->pesanan && $pembayaran->pesanan->id_users === Auth::id()) {
            try {
                $statusPayload = $this->midtrans->getTransactionStatus($orderId);
                $this->applyGatewayStatus($pembayaran, $statusPayload);
            } catch (Throwable $e) {
                Log::warning('Midtrans finish sync failed: ' . $e->getMessage());
            }
        }

        return redirect()
            ->route('customer.orders')
            ->with('status', 'Status pembayaran diperbarui. Silakan cek detail pesanan Anda.');
    }

    /**
     * Customer manual sync button (useful on localhost or delayed webhooks).
     */
    public function syncStatus(Pesanan $pesanan)
    {
        if ($pesanan->id_users !== Auth::id()) {
            return back()->with('error', 'Akses ditolak.');
        }

        $pembayaran = $pesanan->pembayaran;
        if (!$pembayaran || !$pembayaran->gateway_order_id) {
            return back()->with('error', 'Transaksi gateway tidak ditemukan.');
        }

        try {
            $statusPayload = $this->midtrans->getTransactionStatus($pembayaran->gateway_order_id);
            $this->applyGatewayStatus($pembayaran, $statusPayload);
            return back()->with('status', 'Status pembayaran berhasil disinkronkan (' . ($statusPayload['transaction_status'] ?? 'pending') . ').');
        } catch (Throwable $e) {
            return back()->with('error', 'Gagal memeriksa status pembayaran: ' . $e->getMessage());
        }
    }

    private function applyGatewayStatus(Pembayaran $pembayaran, array $payload): void
    {
        $txStatus = (string) ($payload['transaction_status'] ?? 'pending');
        $fraudStatus = (string) ($payload['fraud_status'] ?? 'accept');
        $paymentType = (string) ($payload['payment_type'] ?? $pembayaran->payment_type);

        $newKonfirmasi = match (true) {
            $txStatus === 'settlement' => 1,
            $txStatus === 'capture' && $fraudStatus === 'accept' => 1,
            in_array($txStatus, ['deny', 'cancel', 'expire', 'failure'], true) => 2,
            default => 0,
        };

        DB::transaction(function () use ($pembayaran, $txStatus, $paymentType, $newKonfirmasi) {
            $pesanan = $pembayaran->pesanan;
            $previousKonfirmasi = (int) $pembayaran->status_konfirmasi;

            // Do not downgrade an already settled payment
            if ($previousKonfirmasi === 1) {
                return;
            }

            $pembayaran->update([
                'payment_type' => $paymentType,
                'transaction_status' => $txStatus,
                'status_konfirmasi' => $newKonfirmasi,
                'paid_at' => $newKonfirmasi === 1 ? now() : $pembayaran->paid_at,
            ]);

            if ($newKonfirmasi === 1 && $pesanan->status_pesanan === 'menunggu') {
                $pesanan->update(['status_pesanan' => 'diproses']);
                Notifikasi::create([
                    'id_users' => $pesanan->id_users,
                    'judul' => 'Pembayaran Berhasil',
                    'pesan' => 'Pembayaran Midtrans untuk pesanan ' . $pesanan->no_resi . ' telah diterima. Pesanan sedang diproses.',
                    'is_read' => 0,
                ]);
            } elseif ($newKonfirmasi === 2 && $previousKonfirmasi === 0) {
                // Restore product stock once when payment expires/cancels
                foreach ($pesanan->detailPesanans as $detail) {
                    Produk::where('id_produk', $detail->id_produk)->increment('stok', $detail->qty);
                }
                Notifikasi::create([
                    'id_users' => $pesanan->id_users,
                    'judul' => 'Pembayaran Gagal / Kedaluwarsa',
                    'pesan' => 'Pembayaran untuk pesanan ' . $pesanan->no_resi . ' dibatalkan atau kedaluwarsa (' . $txStatus . ').',
                    'is_read' => 0,
                ]);
            }
        });
    }
}