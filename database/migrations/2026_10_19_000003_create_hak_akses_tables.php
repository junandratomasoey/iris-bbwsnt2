<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hak akses yang bisa diatur Super Admin: izin per jabatan (matriks) dan pengecualian per orang.
 * Baris yang belum ada = memakai bawaan di App\Support\Izin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin_jabatan', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30);
            $table->string('izin', 50);
            $table->boolean('boleh')->default(false);
            $table->timestamps();
            $table->unique(['role', 'izin']);
        });

        Schema::create('izin_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('izin', 50);
            $table->boolean('boleh');
            $table->string('alasan', 255)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'izin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_pengguna');
        Schema::dropIfExists('izin_jabatan');
    }
};
