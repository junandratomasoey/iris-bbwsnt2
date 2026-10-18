<?php

namespace App\Support;

final class Role
{
    public const SUPER_ADMIN = 'super_admin';

    public const PIMPINAN = 'pimpinan';

    public const ADMIN_OP = 'admin_op';

    public const KOORDINATOR = 'koordinator';

    public const MANAGER = 'manager';

    public const PETUGAS = 'petugas';

    public const POB = 'pob';

    public const LABELS = [
        self::SUPER_ADMIN => 'Super Admin',
        self::PIMPINAN => 'Pimpinan',
        self::ADMIN_OP => 'Admin OP',
        self::KOORDINATOR => 'Koordinator Satgas',
        self::MANAGER => 'Manager D.I.',
        self::PETUGAS => 'Petugas Lapangan',
        self::POB => 'Petugas POB',
    ];

    /** Role yang melihat seluruh wilayah. */
    public const GLOBAL = [self::SUPER_ADMIN, self::PIMPINAN, self::ADMIN_OP];

    /** Role yang boleh mengelola master data. */
    public const PENGELOLA = [self::SUPER_ADMIN, self::ADMIN_OP];

    /** Role yang boleh memverifikasi laporan. */
    public const VERIFIKATOR = [self::SUPER_ADMIN, self::ADMIN_OP, self::KOORDINATOR, self::MANAGER];

    /** Role yang mengisi laporan harian. */
    public const PELAPOR = [self::PETUGAS, self::POB, self::MANAGER, self::SUPER_ADMIN, self::ADMIN_OP];

    public static function label(?string $role): string
    {
        return self::LABELS[$role] ?? (string) $role;
    }
}
