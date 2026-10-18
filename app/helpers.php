<?php

if (! function_exists('wa_link')) {
    /** Tautan WhatsApp dari nomor HP Indonesia (08xx → 628xx). */
    function wa_link(?string $hp, ?string $pesan = null): ?string
    {
        $n = preg_replace('/\D/', '', (string) $hp);
        if (strlen($n) < 9) {
            return null;
        }
        if (str_starts_with($n, '0')) {
            $n = '62'.substr($n, 1);
        } elseif (! str_starts_with($n, '62')) {
            $n = '62'.$n;
        }

        return 'https://wa.me/'.$n.($pesan ? '?text='.rawurlencode($pesan) : '');
    }
}

if (! function_exists('angka')) {
    /** Format angka gaya Indonesia: 1.234,5 */
    function angka($v, int $desimal = 0): string
    {
        return $v === null || $v === '' ? '–' : number_format((float) $v, $desimal, ',', '.');
    }
}

if (! function_exists('normalisasi_hp')) {
    function normalisasi_hp(?string $hp): ?string
    {
        $n = preg_replace('/\D/', '', (string) $hp);
        if ($n === '') {
            return null;
        }
        if (str_starts_with($n, '62')) {
            $n = '0'.substr($n, 2);
        }

        return $n;
    }
}
