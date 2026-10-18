<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('petugas')->after('password')->index();
            $table->string('no_hp', 20)->nullable()->after('email')->index();
            $table->string('jabatan', 50)->nullable()->after('role');        // PPA, POB, Manager, Juru, Kabid OP, ...
            $table->foreignId('wilayah_satgas_id')->nullable()->after('jabatan')->constrained('wilayah_satgas')->nullOnDelete();
            $table->foreignId('daerah_irigasi_id')->nullable()->after('wilayah_satgas_id')->constrained('daerah_irigasi')->nullOnDelete();
            $table->string('status_kontak', 30)->nullable()->after('daerah_irigasi_id');
            $table->boolean('is_active')->default(true)->after('status_kontak');
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
        });

        Schema::create('sub_daerah_irigasi_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sub_daerah_irigasi_id')->constrained('sub_daerah_irigasi')->cascadeOnDelete();
            $table->primary(['user_id', 'sub_daerah_irigasi_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sub_daerah_irigasi_user');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('wilayah_satgas_id');
            $table->dropConstrainedForeignId('daerah_irigasi_id');
            $table->dropColumn(['role', 'no_hp', 'jabatan', 'status_kontak', 'is_active', 'must_change_password', 'last_login_at']);
        });
    }
};
