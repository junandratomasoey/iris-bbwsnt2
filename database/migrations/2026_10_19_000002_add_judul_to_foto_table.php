<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Keterangan foto profil D.I. (mis. "Walkthrough 2025 – Bendung Oesao"), tanggal pengambilan, dan urutan tampil. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('foto', function (Blueprint $table) {
            $table->string('judul', 150)->nullable()->after('keterangan');
            $table->date('diambil_pada')->nullable()->after('judul');
            $table->unsignedSmallInteger('urutan')->default(0)->after('diambil_pada');
        });
    }

    public function down(): void
    {
        Schema::table('foto', function (Blueprint $table) {
            $table->dropColumn(['judul', 'diambil_pada', 'urutan']);
        });
    }
};
