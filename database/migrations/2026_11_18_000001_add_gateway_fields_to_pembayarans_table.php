<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->string('bukti_bayar', 150)->nullable()->change();
            $table->string('gateway', 50)->nullable()->after('metode_pembayaran');
            $table->string('gateway_order_id', 100)->nullable()->unique()->after('gateway');
            $table->string('payment_type', 50)->nullable()->after('gateway_order_id');
            $table->string('snap_token', 255)->nullable()->after('payment_type');
            $table->string('payment_url', 255)->nullable()->after('snap_token');
            $table->string('transaction_status', 50)->nullable()->after('payment_url');
            $table->timestamp('paid_at')->nullable()->after('status_konfirmasi');
        });
    }

    public function down(): void
    {
        Schema::table('pembayarans', function (Blueprint $table) {
            $table->dropUnique(['gateway_order_id']);
            $table->dropColumn([
                'gateway',
                'gateway_order_id',
                'payment_type',
                'snap_token',
                'payment_url',
                'transaction_status',
                'paid_at',
            ]);
            $table->string('bukti_bayar', 150)->nullable(false)->change();
        });
    }
};