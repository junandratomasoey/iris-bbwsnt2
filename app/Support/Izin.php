<?php

namespace App\Support;

/**
 * Daftar izin (form/fitur) yang bisa diatur per jabatan dan per orang di menu Hak akses.
 * "bawaan" = jabatan yang mendapat izin ini sebelum diubah. Super Admin selalu punya semua izin.
 * Wilayah kerja (Satgas, D.I., Sub-D.I.) tetap mengikuti penugasan akun, bukan izin ini.
 */
final class Izin
{
    public const GRUP = [
        'form' => 'Form isian lapangan',
        'tindak' => 'Verifikasi & penanganan',
        'master' => 'Data master',
        'sistem' => 'Pengguna, rekap & sistem',
    ];

    public const DAFTAR = [
        'lapor' => [
            'grup' => 'form', 'label' => 'Isi laporan tanaman',
            'ket' => 'Tugas PPA/petugas lapangan: kondisi tanaman per petak tersier, foto, catatan, titik GPS.',
            'bawaan' => [Role::PETUGAS, Role::ADMIN_OP],
        ],
        'lapor-air' => [
            'grup' => 'form', 'label' => 'Isi laporan air (sungai, intake & saluran)',
            'ket' => 'Tugas Manager: debit & TMA sungai dan intake, kondisi saluran, foto, catatan, titik GPS per D.I.',
            'bawaan' => [Role::MANAGER, Role::ADMIN_OP],
        ],
        'isi-pintu' => [
            'grup' => 'form', 'label' => 'Isi pembacaan pintu air',
            'ket' => 'Debit & TMA tiap pintu (rutin Senin dan kejadian khusus).',
            'bawaan' => [Role::PETUGAS, Role::POB, Role::MANAGER, Role::ADMIN_OP],
        ],
        'lapor-kejadian' => [
            'grup' => 'form', 'label' => 'Laporkan kejadian kekeringan',
            'ket' => 'Membuat laporan kejadian baru.',
            'bawaan' => [Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER, Role::PETUGAS, Role::POB],
        ],
        'kelola-laporan' => [
            'grup' => 'tindak', 'label' => 'Ubah & hapus semua laporan petugas',
            'ket' => 'Laporan tanaman (termasuk debit/TMA pintu di dalamnya), laporan air, dan pembacaan pintu milik siapa pun, juga yang sudah disetujui atau lewat batas waktu.',
            'bawaan' => [Role::ADMIN_OP],
        ],
        'verifikasi' => [
            'grup' => 'tindak', 'label' => 'Verifikasi laporan harian',
            'ket' => 'Menyetujui atau meminta perbaikan laporan di wilayahnya.',
            'bawaan' => [Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER],
        ],
        'kelola-kejadian' => [
            'grup' => 'tindak', 'label' => 'Tangani kejadian',
            'ket' => 'Mengubah status penanganan & luas tertangani.',
            'bawaan' => [Role::ADMIN_OP, Role::KOORDINATOR],
        ],
        'lihat-master' => [
            'grup' => 'master', 'label' => 'Lihat menu Infrastruktur, Peta batas D.I., P3A',
            'ket' => 'Daftar infrastruktur, batas D.I. di peta, dan P3A.',
            'bawaan' => [Role::PIMPINAN, Role::ADMIN_OP],
        ],
        'lihat-jadwal' => [
            'grup' => 'master', 'label' => 'Lihat menu Jadwal tanam',
            'ket' => 'Jadwal MT per D.I. dari Bidang OP.',
            'bawaan' => [Role::PIMPINAN, Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER],
        ],
        'kelola-master' => [
            'grup' => 'master', 'label' => 'Ubah data master',
            'ket' => 'D.I., Sub-D.I., infrastruktur, jadwal tanam, P3A, peta batas, foto profil.',
            'bawaan' => [Role::ADMIN_OP],
        ],
        'kelola-pintu' => [
            'grup' => 'master', 'label' => 'Kelola pintu air & skema jaringan',
            'ket' => 'Tambah/ubah pintu air dan rentang normalnya, unggah skema (dalam wilayahnya).',
            'bawaan' => [Role::ADMIN_OP, Role::MANAGER],
        ],
        'lihat-petugas' => [
            'grup' => 'sistem', 'label' => 'Lihat kontak petugas',
            'ket' => 'Direktori petugas lapangan dengan tombol WhatsApp.',
            'bawaan' => [Role::PIMPINAN, Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER],
        ],
        'kelola-petugas' => [
            'grup' => 'sistem', 'label' => 'Tambah/ubah kontak petugas',
            'ket' => 'Akun petugas & POB di wilayahnya, reset password. Pimpinan/Admin OP juga mengatur D.I. & Sub-D.I. Manager.',
            'bawaan' => [Role::PIMPINAN, Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER],
        ],
        'kelola-pengguna' => [
            'grup' => 'sistem', 'label' => 'Kelola semua akun pengguna',
            'ket' => 'Menu Pengguna: semua jabatan kecuali Super Admin.',
            'bawaan' => [Role::ADMIN_OP],
        ],
        'unduh-rekap' => [
            'grup' => 'sistem', 'label' => 'Unduh rekap & ekspor CSV',
            'ket' => 'Rekap periode dan ekspor laporan harian.',
            'bawaan' => [Role::PIMPINAN, Role::ADMIN_OP, Role::KOORDINATOR, Role::MANAGER, Role::PETUGAS, Role::POB],
        ],
    ];

    /** @return array<string> */
    public static function kunci(): array
    {
        return array_keys(self::DAFTAR);
    }

    public static function label(string $izin): string
    {
        return self::DAFTAR[$izin]['label'] ?? $izin;
    }

    public static function bawaan(string $role, string $izin): bool
    {
        return in_array($role, self::DAFTAR[$izin]['bawaan'] ?? [], true);
    }
}
