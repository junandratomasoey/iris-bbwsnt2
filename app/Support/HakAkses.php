<?php

namespace App\Support;

use App\Models\IzinJabatan;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/** Penentu izin: Super Admin → selalu boleh; lalu pengecualian per orang; lalu matriks jabatan; lalu bawaan. */
final class HakAkses
{
    private const CACHE = 'hak-akses:jabatan:v1';

    /** @return array<string, array<string, bool>> role => izin => boleh */
    public static function matriks(): array
    {
        $tersimpan = Cache::rememberForever(self::CACHE, fn () => IzinJabatan::all(['role', 'izin', 'boleh'])
            ->groupBy('role')->map(fn ($r) => $r->pluck('boleh', 'izin')->map(fn ($b) => (bool) $b)->all())->all());

        $hasil = [];
        foreach (array_keys(Role::LABELS) as $role) {
            foreach (Izin::kunci() as $izin) {
                $hasil[$role][$izin] = $role === Role::SUPER_ADMIN
                    || ($tersimpan[$role][$izin] ?? Izin::bawaan($role, $izin));
            }
        }

        return $hasil;
    }

    public static function lupakan(): void
    {
        Cache::forget(self::CACHE);
    }

    public static function boleh(User $u, string $izin): bool
    {
        if ($u->role === Role::SUPER_ADMIN) {
            return true;
        }
        $khusus = $u->izinKhusus();
        if (array_key_exists($izin, $khusus)) {
            return $khusus[$izin];
        }

        return self::matriks()[$u->role][$izin] ?? false;
    }
}
