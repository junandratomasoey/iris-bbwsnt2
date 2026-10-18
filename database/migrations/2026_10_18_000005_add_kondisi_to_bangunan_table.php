<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bangunan', function (Blueprint $table) {
            $table->string('lokasi')->nullable()->after('jenis');          // desa / ruas
            $table->date('kondisi_per')->nullable()->after('kondisi');     // tanggal pemeriksaan kondisi terakhir
            $table->index(['daerah_irigasi_id', 'jenis']);
        });
    }

    public function down(): void
    {
        Schema::table('bangunan', function (Blueprint $table) {
            $table->dropIndex(['daerah_irigasi_id', 'jenis']);
            $table->dropColumn(['lokasi', 'kondisi_per']);
        });
    }
};
