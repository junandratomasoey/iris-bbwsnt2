<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laporan harian per petak tersier: satu laporan per petak per hari.
 * petak_kunci = id pintu tersier, atau 0 untuk laporan tingkat Sub-D.I. (agar indeks unik tetap berlaku).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->foreignId('pintu_air_id')->nullable()->after('sub_daerah_irigasi_id')->constrained('pintu_air')->nullOnDelete();
            $table->unsignedBigInteger('petak_kunci')->default(0)->after('pintu_air_id');
            $table->index('sub_daerah_irigasi_id');
        });
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->dropUnique(['tanggal', 'sub_daerah_irigasi_id']);
            $table->unique(['tanggal', 'sub_daerah_irigasi_id', 'petak_kunci'], 'laporan_harian_tanggal_sub_petak_unique');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->dropUnique('laporan_harian_tanggal_sub_petak_unique');
            $table->unique(['tanggal', 'sub_daerah_irigasi_id']);
        });
        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pintu_air_id');
            $table->dropColumn('petak_kunci');
        });
    }
};
