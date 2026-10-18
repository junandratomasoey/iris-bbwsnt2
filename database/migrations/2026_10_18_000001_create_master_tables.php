<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah_satgas', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 10)->unique();          // I, II, III, IV, V
            $table->string('nama');
            $table->string('cakupan')->nullable();
            $table->timestamps();
        });

        Schema::create('kabupaten', function (Blueprint $table) {
            $table->id();
            $table->string('nama')->unique();
            $table->foreignId('wilayah_satgas_id')->nullable()->constrained('wilayah_satgas')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('daerah_irigasi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('nama');
            $table->json('alias')->nullable();
            $table->foreignId('kabupaten_id')->nullable()->constrained('kabupaten')->nullOnDelete();
            $table->string('kewenangan')->default('Pusat');
            $table->decimal('luas_permen_ha', 12, 2)->nullable();
            $table->decimal('luas_baku_ha', 12, 2)->nullable();
            $table->decimal('luas_potensial_ha', 12, 2)->nullable();
            $table->decimal('luas_fungsional_ha', 12, 2)->nullable();
            $table->string('sumber_air')->nullable();
            $table->string('keterangan')->nullable();      // Lintas Kabupaten / Lintas Negara
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->json('tenaga_op')->nullable();          // pengamat, juru, manajer, ppa, pob
            $table->timestamps();
        });

        Schema::create('sub_daerah_irigasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->string('nama');
            $table->decimal('luas_ha', 12, 2)->nullable();
            $table->string('lokasi')->nullable();           // desa / lokasi bangunan
            $table->foreignId('kabupaten_id')->nullable()->constrained('kabupaten')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['daerah_irigasi_id', 'nama']);
        });

        Schema::create('bangunan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->foreignId('sub_daerah_irigasi_id')->nullable()->constrained('sub_daerah_irigasi')->nullOnDelete();
            $table->string('nama');
            $table->string('jenis', 30);                    // bendung, bendungan, embung, captering, free_intake, bagi, sadap, pintu
            $table->string('kondisi', 30)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('p3a', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('jenis', 10)->default('P3A');   // P3A, GP3A, IP3A
            $table->string('ketua')->nullable();
            $table->string('no_hp', 20)->nullable();
            $table->foreignId('kabupaten_id')->nullable()->constrained('kabupaten')->nullOnDelete();
            $table->foreignId('daerah_irigasi_id')->nullable()->constrained('daerah_irigasi')->nullOnDelete();
            $table->foreignId('sub_daerah_irigasi_id')->nullable()->constrained('sub_daerah_irigasi')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('jadwal_tanam', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daerah_irigasi_id')->constrained('daerah_irigasi')->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('mt');              // 1, 2, 3
            $table->date('mulai');
            $table->date('selesai');
            $table->string('komoditas')->default('Padi');
            $table->decimal('rencana_tanam_ha', 12, 2)->nullable();
            $table->decimal('realisasi_tanam_ha', 12, 2)->nullable();
            $table->decimal('puso_ha', 12, 2)->nullable();
            $table->decimal('panen_ha', 12, 2)->nullable();
            $table->string('sumber')->default('Bidang OP');
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->unique(['daerah_irigasi_id', 'tahun', 'mt']);
        });
    }

    public function down(): void
    {
        foreach (['jadwal_tanam', 'p3a', 'bangunan', 'sub_daerah_irigasi', 'daerah_irigasi', 'kabupaten', 'wilayah_satgas'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
