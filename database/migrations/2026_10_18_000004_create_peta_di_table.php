<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Batas areal D.I. (poligon) per lapisan peta. File public/geojson/{lapisan}.geojson dibangun ulang dari tabel ini.
        Schema::create('peta_di', function (Blueprint $table) {
            $table->id();
            $table->string('lapisan', 15)->index();          // fungsional, baku, potensial
            $table->foreignId('daerah_irigasi_id')->nullable()->constrained('daerah_irigasi')->nullOnDelete();
            $table->string('nama');
            $table->decimal('luas_ha', 12, 2)->default(0);
            $table->decimal('lat', 10, 7)->nullable();       // titik tengah
            $table->decimal('lng', 10, 7)->nullable();
            $table->longText('geometri');                    // GeoJSON geometry (Polygon/MultiPolygon, WGS84)
            $table->string('sumber')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peta_di');
    }
};
