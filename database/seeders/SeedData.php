<?php

namespace Database\Seeders;

use App\Models\Kabupaten;
use Illuminate\Support\Str;

/** Pembaca data hasil ekstraksi Excel (database/seeders/data/master.json). */
final class SeedData
{
    private static ?array $data = null;

    public static function get(string $key): array
    {
        self::$data ??= json_decode(file_get_contents(__DIR__.'/data/master.json'), true);

        return self::$data[$key] ?? [];
    }

    /** Nama kabupaten dibakukan agar sumber Excel yang berbeda ejaan tetap cocok. */
    public static function namaKabupaten(?string $nama): ?string
    {
        if (! $nama) {
            return null;
        }
        $n = trim(preg_replace('/^(KAB(UPATEN)?\.?)\s*/i', '', $nama));
        $n = Str::title(Str::lower($n));
        $koreksi = [
            'Nagakeo' => 'Nagekeo', 'Sabu Rai Jua' => 'Sabu Raijua', 'Sabu Raijua' => 'Sabu Raijua',
            'Tts' => 'Timor Tengah Selatan', 'Ttu' => 'Timor Tengah Utara', 'Sbd' => 'Sumba Barat Daya',
        ];

        return $koreksi[$n] ?? $n;
    }

    public static function kabupatenId(?string $nama): ?int
    {
        $n = self::namaKabupaten($nama);

        return $n ? Kabupaten::firstOrCreate(['nama' => $n])->id : null;
    }

    /** Kunci pencocokan nama D.I./Sub-D.I. (huruf kecil, tanpa awalan D.I. dan tanda baca). */
    public static function kunci(?string $nama): string
    {
        $n = Str::lower((string) $nama);
        $n = str_replace(['d.i.', 'd.i', 'di.'], '', $n);
        $n = preg_replace('/^di\s/', '', trim($n));

        return preg_replace('/[^a-z0-9]/', '', $n);
    }
}
