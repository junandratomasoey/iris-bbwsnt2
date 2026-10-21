<?php

namespace App\Support;

/**
 * Kode isian sesuai Format Pengisian Manager Air dan Format Pengisian PPA (BBWS NT II).
 * Kunci = kode yang ditulis petugas di lembar isian.
 */
final class KodeIsian
{
    public const JENIS_LAPOR = ['rutin' => 'Rutin (Senin)', 'insidental' => 'Insidental'];

    public const ALASAN_PPA = [
        'panen' => 'Panen', 'pengolahan' => 'Pengolahan lahan', 'banjir' => 'Banjir',
        'kerusakan_pintu' => 'Kerusakan pintu', 'kekurangan_air' => 'Kekurangan air', 'lainnya' => 'Lainnya',
    ];

    public const ALASAN_MANAGER = [
        'banjir' => 'Banjir', 'kekurangan_air' => 'Kekurangan air', 'pola_operasi' => 'Perubahan pola operasi',
        'kerusakan_bangunan' => 'Kerusakan bangunan', 'lainnya' => 'Lainnya',
    ];

    public const MT = [1 => 'MT I', 2 => 'MT II', 3 => 'MT III'];

    public const CUACA = ['cerah' => 'Cerah', 'berawan' => 'Berawan', 'hujan_ringan' => 'Hujan ringan', 'hujan_lebat' => 'Hujan lebat'];

    public const HUJAN_7_HARI = ['tidak' => 'Tidak ada', '1-2' => '1–2 hari', '3-5' => '3–5 hari', 'hampir' => 'Hampir setiap hari'];

    public const TINDAKAN_PINTU = ['tetap' => 'Tidak ada perubahan', 'dibuka' => 'Dibuka/ditambah', 'dikurangi' => 'Dikurangi', 'ditutup' => 'Ditutup'];

    public const PEMBERIAN_AIR = ['terus' => 'Terus-menerus', 'rotasi' => 'Rotasi/giliran', 'dihentikan' => 'Dihentikan'];

    public const KONDISI_BENDUNG = ['B' => 'Baik', 'RR' => 'Rusak ringan', 'RB' => 'Rusak berat', 'G' => 'Gerusan di hilir'];

    public const KONDISI_PINTU = ['B' => 'Baik', 'RR' => 'Rusak ringan (macet/berat diputar)', 'RB' => 'Rusak berat (tidak bisa dioperasikan)', 'H' => 'Hilang'];

    public const KANTONG_LUMPUR = ['B' => 'Bersih', 'Sd' => 'Sedimen sedang', 'Pn' => 'Penuh, perlu dikuras'];

    public const KONDISI_SALURAN = ['N' => 'Normal', 'S' => 'Sedimentasi', 'Sp' => 'Sampah/gulma', 'Bc' => 'Bocor/longsor', 'K' => 'Kering'];

    public const JENIS_TANAMAN = ['P' => 'Padi', 'J' => 'Jagung', 'K' => 'Kacang-kacangan', 'Pl' => 'Palawija lain', 'B' => 'Bera (tidak ditanam)'];

    public const FASE = [
        0 => 'Pengolahan lahan', 1 => 'Persemaian', 2 => 'Vegetatif awal (0–30 HST)', 3 => 'Vegetatif akhir (31–60 HST)',
        4 => 'Generatif/berbunga (61–90 HST)', 5 => 'Pemasakan/siap panen', 6 => 'Sudah panen',
    ];

    public const KECUKUPAN_AIR = [
        'C' => 'Cukup', 'KR' => 'Kurang (tanah lembab, tidak tergenang)', 'KS' => 'Kering sedang (tanah retak halus)',
        'KB' => 'Kering berat (retak lebar/tanaman layu)', 'Bj' => 'Kelebihan air/banjir',
    ];

    /** Kecukupan air → status kekeringan. Kelebihan air bukan kekeringan (Aman), ditandai terpisah. */
    public const STATUS_KECUKUPAN = [
        'C' => StatusKondisi::AMAN, 'KR' => StatusKondisi::WASPADA, 'KS' => StatusKondisi::SIAGA,
        'KB' => StatusKondisi::KRITIS, 'Bj' => StatusKondisi::AMAN,
    ];

    /** Kondisi saluran titik ukur primer → status (sementara, menunggu ketetapan Bidang OP). */
    public const STATUS_SALURAN = [
        'N' => StatusKondisi::AMAN, 'S' => StatusKondisi::WASPADA, 'Sp' => StatusKondisi::WASPADA,
        'Bc' => StatusKondisi::WASPADA, 'K' => StatusKondisi::SIAGA,
    ];

    public const DOKUMENTASI_PPA = [
        'papan_duga' => 'Foto papan duga (TMA) tiap pintu', 'bukaan' => 'Foto bukaan pintu', 'saluran' => 'Foto kondisi saluran',
        'sawah' => 'Foto kondisi sawah tiap petak tersier', 'masalah' => 'Foto permasalahan (jika ada)',
    ];

    public const DOKUMENTASI_MANAGER = [
        'papan_duga' => 'Papan duga hulu bendung', 'intake' => 'Pintu intake dan penguras',
        'titik_ukur' => 'Tiap titik ukur saluran primer', 'masalah' => 'Permasalahan (jika ada)',
    ];

    /** Pintu bermasalah: rusak/hilang, atau saluran di pintu itu tidak normal. */
    public static function pintuBermasalah(?string $kondisiPintu, ?string $kondisiSaluran): bool
    {
        return in_array($kondisiPintu, ['RR', 'RB', 'H'], true) || in_array($kondisiSaluran, ['S', 'Sp', 'Bc', 'K'], true);
    }

    /** Untuk tabel: "KR · Kurang" (tanpa keterangan dalam kurung). */
    public static function singkat(array $daftar, $kode): ?string
    {
        $t = self::teks($daftar, $kode);

        return $t === null ? null : trim(preg_replace('/\s*\(.*\)$/', '', $t));
    }

    /** "KR" → "KR · Kurang (tanah lembab…)" */
    public static function teks(array $daftar, $kode, bool $denganKode = true): ?string
    {
        if ($kode === null || $kode === '' || ! array_key_exists($kode, $daftar)) {
            return null;
        }

        return $denganKode ? $kode.' · '.$daftar[$kode] : $daftar[$kode];
    }
}
