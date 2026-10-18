<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bangunan', function (Blueprint $table) {
            $table->string('kode_aset', 40)->nullable()->after('nama');   // kode integrasi SIGA/BMN
            $table->json('data_teknis')->nullable()->after('keterangan'); // {label: nilai} dari SIGA/shapefile
        });
    }

    public function down(): void
    {
        Schema::table('bangunan', function (Blueprint $table) {
            $table->dropColumn(['kode_aset', 'data_teknis']);
        });
    }
};
