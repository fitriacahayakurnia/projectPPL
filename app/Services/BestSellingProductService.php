<?php

namespace App\Services;

use App\Models\DetailPesanan;
use Illuminate\Support\Collection;

class BestSellingProductService
{
    /**
     * Calculate best-selling products ranked by total units sold (SUM of qty).
     *
     * Exposes `total_terjual`, `total_qty`, and `total_nominal` consistently
     * so storefront and dashboard consumers share one calculation component.
     *
     * @param  array<int, string>  $statuses
     */
    public function calculate(
        int $limit = 10,
        int $minSold = 1,
        array $statuses = ['dikirim', 'selesai']
    ): Collection {
        $query = DetailPesanan::query()
            ->join('pesanans', 'detail_pesanans.id_pesanan', '=', 'pesanans.id_pesanan')
            ->whereIn('pesanans.status_pesanan', $statuses)
            ->select('detail_pesanans.id_produk')
            ->selectRaw('SUM(detail_pesanans.qty) as total_terjual')
            ->selectRaw('SUM(detail_pesanans.qty) as total_qty')
            ->selectRaw('SUM(detail_pesanans.qty * detail_pesanans.harga_beli) as total_nominal')
            ->groupBy('detail_pesanans.id_produk')
            ->orderByDesc('total_terjual')
            ->with([
                'produk' => fn ($q) => $q
                    ->select('id_produk', 'id_kategori', 'gambar', 'nama_produk', 'harga', 'stok')
                    ->with(['kategori' => fn ($kq) => $kq->select('id_kategori', 'nama_kategori')]),
            ])
            ->take($limit);

        if ($minSold > 1) {
            $query->having('total_terjual', '>=', $minSold);
        }

        return $query->get();
    }

    /**
     * Best-selling products for the customer landing page (minimum 10 units sold).
     */
    public function forStorefront(int $limit = 10, int $minSold = 10): Collection
    {
        return $this->calculate(
            limit: $limit,
            minSold: $minSold,
            statuses: ['dikirim', 'selesai']
        );
    }

    /**
     * Top-performing products for the Owner revenue & sales dashboard.
     */
    public function forOwnerDashboard(int $limit = 5): Collection
    {
        return $this->calculate(
            limit: $limit,
            minSold: 1,
            statuses: ['selesai']
        );
    }
}