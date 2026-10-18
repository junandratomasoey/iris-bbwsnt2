<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian luas tanam per komoditas: padi, palawija, hortikultura.
 * luas_tanam_ha tetap ada sebagai total (dipakai aturan status, peta, dan rekap).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->decimal('luas_padi_ha', 10, 2)->default(0)->after('luas_tanam_ha');
            $table->decimal('luas_palawija_ha', 10, 2)->default(0)->after('luas_padi_ha');
            $table->decimal('luas_horti_ha', 10, 2)->default(0)->after('luas_palawija_ha');
            $table->string('jenis_palawija', 150)->nullable()->after('luas_horti_ha');  // mis. "Jagung, Kacang hijau"
            $table->string('jenis_horti', 150)->nullable()->after('jenis_palawija');    // mis. "Cabai, Bawang merah"
        });

        // laporan lama dianggap seluruhnya padi
        DB::table('laporan_harian')->update(['luas_padi_ha' => DB::raw('luas_tanam_ha')]);
    }

    public function down(): void
    {
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->dropColumn(['luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'jenis_palawija', 'jenis_horti']);
        });
    }
};
