<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan_harian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('sub_daerah_irigasi_id')->constrained('sub_daerah_irigasi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('mt')->nullable();
            // air
            $table->decimal('debit_intake_lps', 10, 2);
            $table->decimal('tma_cm', 8, 2);
            $table->string('kondisi_saluran', 10);            // penuh, normal, kurang, kering
            $table->unsignedTinyInteger('bukaan_pintu_persen')->nullable();
            // lahan
            $table->string('fase_tanaman', 20)->nullable();   // bera, persiapan, vegetatif, generatif, pemasakan, panen
            $table->decimal('luas_tanam_ha', 10, 2)->default(0);
            $table->decimal('luas_hijau_ha', 10, 2)->default(0);
            $table->decimal('luas_kering_ha', 10, 2)->default(0);
            $table->decimal('luas_panen_ha', 10, 2)->default(0);
            $table->decimal('luas_puso_ha', 10, 2)->default(0);
            $table->text('kendala')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            // verifikasi
            $table->string('status_verifikasi', 15)->default('menunggu')->index(); // menunggu, disetujui, perbaikan
            $table->text('catatan_verifikasi')->nullable();
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->timestamps();
            $table->unique(['tanggal', 'sub_daerah_irigasi_id']);
            $table->index('tanggal');
        });

        Schema::create('kejadian', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal_lapor');
            $table->foreignId('daerah_irigasi_id')->nullable()->constrained('daerah_irigasi')->nullOnDelete();
            $table->foreignId('sub_daerah_irigasi_id')->nullable()->constrained('sub_daerah_irigasi')->nullOnDelete();
            $table->foreignId('kabupaten_id')->nullable()->constrained('kabupaten')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kecamatan')->nullable();
            $table->string('desa')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('jenis_kegagalan', 60);           // Gagal Tanam, Gagal Panen, Kerusakan Jaringan, Lainnya
            $table->string('penyebab')->nullable();
            $table->string('status_kegagalan', 100)->default('Potensi'); // Potensi, Terjadi
            $table->decimal('luas_terdampak_ha', 10, 2)->default(0);
            $table->decimal('luas_tertangani_ha', 10, 2)->default(0);
            $table->text('sumber_air')->nullable();
            $table->text('solusi')->nullable();
            $table->string('p3a')->nullable();
            $table->string('pelapor_nama')->nullable();
            $table->string('pelapor_hp', 20)->nullable();
            $table->string('sumber_data')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status_penanganan', 30)->default('Perlu Penanganan')->index();
            $table->timestamps();
        });

        Schema::create('foto', function (Blueprint $table) {
            $table->id();
            $table->morphs('fotoable');
            $table->string('path');
            $table->string('keterangan')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->nullableMorphs('subject');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'foto', 'kejadian', 'laporan_harian'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
