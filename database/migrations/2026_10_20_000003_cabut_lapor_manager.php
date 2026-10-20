<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Laporan harian (kondisi tanaman) adalah tugas PPA. Manager D.I. tidak lagi mengisinya;
 * Manager mengisi pembacaan pintu (intake/primer) dan memverifikasi laporan PPA.
 * Juga menimpa isian matriks Hak akses yang sudah tersimpan untuk Manager.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('izin_jabatan')->where('role', 'manager')->where('izin', 'lapor')->update(['boleh' => false, 'updated_at' => now()]);
        Cache::forget('hak-akses:jabatan:v1');
    }

    public function down(): void
    {
        DB::table('izin_jabatan')->where('role', 'manager')->where('izin', 'lapor')->update(['boleh' => true, 'updated_at' => now()]);
        Cache::forget('hak-akses:jabatan:v1');
    }
};
