<?php

namespace App\Support;

use App\Models\LaporanHarian;

/**
 * Status kondisi tahap 1 (aturan sederhana dari laporan harian).
 * Tahap 2 akan diganti Indeks Risiko Kekeringan (IRK) berbasis neraca air.
 */
final class StatusKondisi
{
    public const AMAN = 'aman';

    public const WASPADA = 'waspada';

    public const SIAGA = 'siaga';

    public const KRITIS = 'kritis';

    public const KOSONG = 'kosong';

    public const URUTAN = [self::KOSONG => 0, self::AMAN => 1, self::WASPADA => 2, self::SIAGA => 3, self::KRITIS => 4];

    public const LABEL = [
        self::AMAN => 'Aman',
        self::WASPADA => 'Waspada',
        self::SIAGA => 'Siaga',
        self::KRITIS => 'Kritis',
        self::KOSONG => 'Belum ada laporan',
    ];

    public const WARNA = [
        self::AMAN => '#4E8A2E',
        self::WASPADA => '#D4A017',
        self::SIAGA => '#D9622B',
        self::KRITIS => '#B3261E',
        self::KOSONG => '#9AA4A8',
    ];

    /** Laporan lebih tua dari ini dianggap tidak mewakili kondisi terkini. */
    public const MASA_BERLAKU_HARI = 3;

    public static function dariLaporan(?LaporanHarian $l): string
    {
        if (! $l) {
            return self::KOSONG;
        }
        if ($l->getAttribute('status_gabungan')) {
            return $l->getAttribute('status_gabungan');
        }

        return self::dariNilai($l->kondisi_saluran, (float) $l->luas_tanam_ha, (float) $l->luas_kering_ha, (float) $l->luas_puso_ha);
    }

    /** Aturan yang sama dengan dariLaporan(), untuk baris hasil query mentah (rekap periode). */
    public static function dariNilai(?string $kondisi, float $tanam, float $kering, float $puso): string
    {
        $status = match ($kondisi) {
            'penuh', 'normal' => self::AMAN,
            'kurang' => self::WASPADA,
            'kering' => self::SIAGA,
            default => self::KOSONG,
        };

        $rasioKering = $tanam > 0 ? $kering / $tanam : 0;

        if ($puso > 0 || $rasioKering >= 0.5) {
            return self::KRITIS;
        }
        if ($rasioKering >= 0.25) {
            $status = self::maks($status, self::SIAGA);
        } elseif ($rasioKering >= 0.10) {
            $status = self::maks($status, self::WASPADA);
        }

        return $status;
    }

    public static function maks(string $a, string $b): string
    {
        return self::URUTAN[$a] >= self::URUTAN[$b] ? $a : $b;
    }

    public static function label(string $s): string
    {
        return self::LABEL[$s] ?? $s;
    }
}
