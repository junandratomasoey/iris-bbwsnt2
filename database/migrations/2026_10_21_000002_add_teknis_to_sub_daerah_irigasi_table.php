<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Data teknis Sub-D.I. untuk peta publik: luas fungsional, cakupan wilayah, saluran, dan batas areal (GeoJSON). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_daerah_irigasi', function (Blueprint $t) {
            $t->decimal('luas_fungsional_ha', 12, 2)->nullable()->after('luas_ha');
            $t->string('kecamatan', 255)->nullable()->after('lokasi');
            $t->string('desa', 500)->nullable()->after('kecamatan');            // desa cakupan, dipisah koma
            $t->string('saluran', 255)->nullable()->after('desa');               // nama saluran sekunder/induk yang melayani
            $t->decimal('panjang_saluran_m', 10, 1)->nullable()->after('saluran');
            $t->longText('geometri')->nullable()->after('lng');                 // batas areal Sub-D.I. (GeoJSON Polygon/MultiPolygon)
        });
    }

    public function down(): void
    {
        Schema::table('sub_daerah_irigasi', function (Blueprint $t) {
            $t->dropColumn(['luas_fungsional_ha', 'kecamatan', 'desa', 'saluran', 'panjang_saluran_m', 'geometri']);
        });
    }
};
