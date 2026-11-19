<?php

namespace App\Http\Controllers;

use App\Models\AlamatUser;
use App\Models\Ekspedisi;
use App\Models\Keranjang;
use App\Models\Produk;
use App\Services\RajaOngkirService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EkspedisiController extends Controller
{
    public function calculateRates(Request $request, RajaOngkirService $rajaOngkir)
    {
        $data = $request->validate([
            'id_alamat' => ['required', 'integer', 'exists:alamat_users,id_alamat'],
        ]);

        $alamat = AlamatUser::where('id_users', Auth::id())
            ->where('id_alamat', $data['id_alamat'])
            ->first();

        if (!$alamat) {
            return response()->json(['message' => 'Alamat tidak valid.'], 403);
        }

        $totalWeight = $this->calculateCurrentCheckoutWeight();
        $ekspedisis = Ekspedisi::select('id_ekspedisi', 'nama_ekspedisi', 'biaya_pengiriman')->get();

        $result = $rajaOngkir->calculateRates($alamat, $totalWeight, $ekspedisis);

        return response()->json($result);
    }

    private function calculateCurrentCheckoutWeight(): int
    {
        $buyNowItem = session('buy_now_item');
        if ($buyNowItem) {
            $produk = Produk::select('id_produk', 'berat')->find($buyNowItem['id_produk']);
            $unitWeight = max(1, (int) ($produk?->berat ?? 1000));
            return $unitWeight * max(1, (int) $buyNowItem['qty']);
        }

        $keranjang = Keranjang::where('id_users', Auth::id())
            ->with('detailKeranjangs.produk:id_produk,berat')
            ->first();

        if (!$keranjang) {
            return 1000;
        }

        $total = 0;
        foreach ($keranjang->detailKeranjangs as $detail) {
            if ($detail->produk) {
                $unitWeight = max(1, (int) ($detail->produk->berat ?? 1000));
                $total += $unitWeight * $detail->qty;
            }
        }

        return max(1000, $total);
    }
}