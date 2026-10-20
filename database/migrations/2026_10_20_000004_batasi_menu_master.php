<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Infrastruktur, Peta batas D.I., dan P3A hanya untuk Super Admin, Pimpinan, Admin OP.
 * Jadwal tanam dipisah ke izin sendiri (lihat-jadwal) dengan pengaturan yang sama seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // jadwal tanam mewarisi isian lama "lihat-master" agar aksesnya tidak berubah
        foreach (DB::table('izin_jabatan')->where('izin', 'lihat-master')->get() as $r) {
            DB::table('izin_jabatan')->updateOrInsert(['role' => $r->role, 'izin' => 'lihat-jadwal'], ['boleh' => $r->boleh, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach (DB::table('izin_pengguna')->where('izin', 'lihat-master')->get() as $r) {
            DB::table('izin_pengguna')->updateOrInsert(['user_id' => $r->user_id, 'izin' => 'lihat-jadwal'], ['boleh' => $r->boleh, 'alasan' => $r->alasan, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('izin_jabatan')->where('izin', 'lihat-master')->whereNotIn('role', ['pimpinan', 'admin_op'])->update(['boleh' => false, 'updated_at' => now()]);
        Cache::forget('hak-akses:jabatan:v1');
    }

    public function down(): void
    {
        DB::table('izin_jabatan')->where('izin', 'lihat-jadwal')->delete();
        DB::table('izin_pengguna')->where('izin', 'lihat-jadwal')->delete();
        Cache::forget('hak-akses:jabatan:v1');
    }
};
