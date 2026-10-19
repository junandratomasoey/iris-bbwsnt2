<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pintu air per D.I./Sub-D.I. dengan rentang normal debit & TMA yang diisi sendiri,
 * pembacaan berkala (rutin Senin + kejadian khusus), dan file skema jaringan irigasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pintu_air', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->foreignId('sub_daerah_irigasi_id')->nullable()->constrained('sub_daerah_irigasi')->nullOnDelete();
            $table->foreignId('bangunan_id')->nullable()->constrained('bangunan')->nullOnDelete();
            $table->string('kode', 30);
            $table->string('nama')->nullable();
            $table->string('jenis', 20)->default('sadap');
            $table->string('saluran')->nullable();
            $table->decimal('luas_layanan_ha', 10, 2)->nullable();
            $table->decimal('debit_min_lps', 10, 2)->nullable();
            $table->decimal('debit_maks_lps', 10, 2)->nullable();
            $table->decimal('tma_min_cm', 8, 2)->nullable();
            $table->decimal('tma_maks_cm', 8, 2)->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['daerah_irigasi_id', 'kode']);
        });

        Schema::create('sesi_pintu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained();
            $table->date('tanggal');
            $table->time('jam')->nullable();
            $table->string('jenis', 20)->default('rutin');
            $table->text('catatan')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['daerah_irigasi_id', 'tanggal']);
        });

        Schema::create('pembacaan_pintu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sesi_pintu_id')->constrained('sesi_pintu')->cascadeOnDelete();
            $table->foreignId('pintu_air_id')->constrained('pintu_air')->cascadeOnDelete();
            $table->date('tanggal');
            $table->decimal('debit_lps', 10, 2)->nullable();
            $table->decimal('tma_cm', 8, 2)->nullable();
            $table->decimal('bukaan_cm', 8, 2)->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();
            $table->index(['pintu_air_id', 'tanggal']);
        });

        Schema::create('skema_jaringan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->string('judul', 150);
            $table->string('path');
            $table->string('mime', 60);
            $table->unsignedInteger('ukuran');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skema_jaringan');
        Schema::dropIfExists('pembacaan_pintu');
        Schema::dropIfExists('sesi_pintu');
        Schema::dropIfExists('pintu_air');
    }
};
