<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Debit rencana (Q rencana) per pintu dari skema jaringan, untuk membandingkan debit terbaca dengan rencana. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pintu_air', function (Blueprint $table) {
            $table->decimal('debit_rencana_lps', 10, 2)->nullable()->after('luas_layanan_ha');
        });
    }

    public function down(): void
    {
        Schema::table('pintu_air', function (Blueprint $table) {
            $table->dropColumn('debit_rencana_lps');
        });
    }
};
