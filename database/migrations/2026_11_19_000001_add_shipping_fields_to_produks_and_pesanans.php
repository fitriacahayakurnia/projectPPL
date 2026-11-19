<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->unsignedInteger('berat')->default(1000)->after('stok');
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->string('layanan_pengiriman', 100)->nullable()->after('id_ekspedisi');
            $table->string('estimasi_hari', 50)->nullable()->after('layanan_pengiriman');
        });
    }

    public function down(): void
    {
        Schema::table('produks', function (Blueprint $table) {
            $table->dropColumn('berat');
        });

        Schema::table('pesanans', function (Blueprint $table) {
            $table->dropColumn(['layanan_pengiriman', 'estimasi_hari']);
        });
    }
};