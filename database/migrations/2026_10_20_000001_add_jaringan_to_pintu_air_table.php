<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur jaringan: tingkat pintu (utama/primer/sekunder/tersier) dan pintu hulunya,
 * titik koordinat yang diambil petugas di lapangan, serta penanggung jawab per orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pintu_air', function (Blueprint $table) {
            $table->string('tingkat', 12)->default('sekunder')->after('jenis');
            $table->foreignId('hulu_id')->nullable()->after('tingkat')->constrained('pintu_air')->nullOnDelete();
            $table->foreignId('titik_oleh')->nullable()->after('lng')->constrained('users')->nullOnDelete();
            $table->timestamp('titik_pada')->nullable()->after('titik_oleh');
            $table->unsignedSmallInteger('titik_akurasi_m')->nullable()->after('titik_pada');
        });
        DB::table('pintu_air')->whereIn('jenis', ['intake', 'penguras'])->update(['tingkat' => 'utama']);
        DB::table('pintu_air')->where('jenis', 'tersier')->update(['tingkat' => 'tersier']);

        Schema::create('pintu_air_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pintu_air_id')->constrained('pintu_air')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('peran', 10)->default('utama'); // utama | pengganti
            $table->timestamps();
            $table->unique(['pintu_air_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pintu_air_user');
        Schema::table('pintu_air', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hulu_id');
            $table->dropConstrainedForeignId('titik_oleh');
            $table->dropColumn(['tingkat', 'titik_pada', 'titik_akurasi_m']);
        });
    }
};
