<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Pembacaan pintu (debit & TMA mistar) yang diisi PPA bersama laporan harian tanaman. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_pintu', function (Blueprint $table) {
            $table->foreignId('laporan_harian_id')->nullable()->after('user_id')->constrained('laporan_harian')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sesi_pintu', function (Blueprint $table) {
            $table->dropConstrainedForeignId('laporan_harian_id');
        });
    }
};
