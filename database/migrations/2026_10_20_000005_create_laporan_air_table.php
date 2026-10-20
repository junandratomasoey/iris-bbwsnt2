<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Form laporan dipisah:
 *  - laporan_air (Manager): air sungai, intake & saluran per D.I. per hari;
 *  - laporan_harian (PPA): kondisi tanaman per petak; kolom air jadi opsional (tetap ada untuk data lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_air', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('mt')->nullable();
            $table->decimal('debit_sungai_m3s', 10, 3)->nullable();
            $table->decimal('tma_sungai_cm', 8, 2)->nullable();
            $table->decimal('debit_intake_lps', 10, 2);
            $table->decimal('tma_intake_cm', 8, 2)->nullable();
            $table->string('kondisi_saluran', 10);
            $table->unsignedTinyInteger('bukaan_pintu_persen')->nullable();
            $table->text('catatan')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->unique(['tanggal', 'daerah_irigasi_id']);
            $table->index('tanggal');
        });

        Schema::table('laporan_harian', function (Blueprint $table) {
            $table->decimal('debit_intake_lps', 10, 2)->nullable()->change();
            $table->decimal('tma_cm', 8, 2)->nullable()->change();
            $table->string('kondisi_saluran', 10)->nullable()->change();
        });

        // izin: laporan air untuk Manager; laporan tanaman hanya PPA/petugas lapangan (+ Admin OP);
        // log aktivitas tidak lagi bisa diatur (selalu hanya Super Admin)
        DB::table('izin_jabatan')->where('izin', 'lapor')->where('role', 'pob')->update(['boleh' => false, 'updated_at' => now()]);
        DB::table('izin_jabatan')->where('izin', 'kelola-petugas')->where('role', 'pimpinan')->update(['boleh' => true, 'updated_at' => now()]);
        DB::table('izin_jabatan')->where('izin', 'lihat-audit')->delete();
        DB::table('izin_pengguna')->where('izin', 'lihat-audit')->delete();
        Cache::forget('hak-akses:jabatan:v1');
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_air');
        Cache::forget('hak-akses:jabatan:v1');
    }
};
