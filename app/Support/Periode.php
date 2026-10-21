<?php

namespace App\Support;

use App\Models\JadwalTanam;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Periode tampilan dashboard & peta.
 * hari_ini = kondisi terkini (laporan 7 hari terakhir); lainnya = rekap rentang tanggal.
 */
final class Periode
{
    public const PILIHAN = [
        'hari_ini' => 'Hari ini',
        '7hari' => '7 hari',
        'bulan' => 'Bulan ini',
        'mt' => 'MT berjalan',
        'tahun' => 'Tahun ini',
        'rentang' => 'Pilih tanggal',
    ];

    /** Rentang terpanjang yang dihitung sekaligus (hari). */
    public const MAKS_HARI = 400;

    public function __construct(
        public readonly string $kode,
        public readonly Carbon $dari,
        public readonly Carbon $sampai,
        public readonly string $label,
    ) {}

    public static function dariRequest(Request $request): self
    {
        $kode = array_key_exists($request->input('periode'), self::PILIHAN) ? $request->input('periode') : 'hari_ini';
        $hariIni = today();
        // kompatibel dengan ?tanggal= lama (peta): kondisi pada tanggal itu
        $acuan = $request->date('tanggal')?->startOfDay();
        $acuan = $acuan && $acuan->lte($hariIni) ? $acuan : $hariIni;

        return match ($kode) {
            '7hari' => new self($kode, $hariIni->copy()->subDays(6), $hariIni, '7 hari terakhir'),
            'bulan' => new self($kode, $hariIni->copy()->startOfMonth(), $hariIni, $hariIni->translatedFormat('F Y')),
            'tahun' => new self($kode, $hariIni->copy()->startOfYear(), $hariIni, 'Tahun '.$hariIni->year),
            'mt' => self::mtBerjalan($hariIni),
            'rentang' => self::rentang($request, $hariIni),
            default => new self('hari_ini', $acuan, $acuan, $acuan->isSameDay($hariIni) ? 'Hari ini' : $acuan->translatedFormat('d M Y')),
        };
    }

    public function rekap(): bool
    {
        return $this->kode !== 'hari_ini';
    }

    public function jumlahHari(): int
    {
        return (int) $this->dari->diffInDays($this->sampai) + 1;
    }

    /** Kalender & tren per minggu bila rentang lebih dari ~2 bulan. */
    public function mingguan(): bool
    {
        return $this->jumlahHari() > 62;
    }

    public function keterangan(): string
    {
        if (! $this->rekap()) {
            return $this->sampai->translatedFormat('l, d F Y');
        }

        return $this->dari->translatedFormat('d M Y').' – '.$this->sampai->translatedFormat('d M Y').' · '.$this->jumlahHari().' hari';
    }

    /** @return array<string, string> parameter URL untuk mempertahankan periode */
    public function query(): array
    {
        return $this->kode === 'rentang'
            ? ['periode' => 'rentang', 'dari' => $this->dari->toDateString(), 'sampai' => $this->sampai->toDateString()]
            : ['periode' => $this->kode];
    }

    private static function rentang(Request $request, Carbon $hariIni): self
    {
        $sampai = $request->date('sampai')?->startOfDay() ?? $hariIni->copy();
        $sampai = $sampai->gt($hariIni) ? $hariIni->copy() : $sampai;
        $dari = $request->date('dari')?->startOfDay() ?? $sampai->copy()->subDays(29);
        if ($dari->gt($sampai)) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        if ($dari->diffInDays($sampai) >= self::MAKS_HARI) {
            $dari = $sampai->copy()->subDays(self::MAKS_HARI - 1);
        }

        return new self('rentang', $dari, $sampai, $dari->translatedFormat('d M Y').' – '.$sampai->translatedFormat('d M Y'));
    }

    /** MT yang sedang dijalani paling banyak D.I.; dari tanggal mulai paling awal sampai hari ini. */
    private static function mtBerjalan(Carbon $hariIni): self
    {
        $aktif = JadwalTanam::whereDate('mulai', '<=', $hariIni)->whereDate('selesai', '>=', $hariIni)->get(['mt', 'tahun', 'mulai']);
        if ($aktif->isEmpty()) {
            return new self('mt', $hariIni->copy()->startOfMonth(), $hariIni, 'MT berjalan (belum ada jadwal, ditampilkan bulan ini)');
        }
        $mt = $aktif->groupBy('mt')->sortByDesc(fn ($g) => $g->count())->keys()->first();
        $grup = $aktif->where('mt', $mt);
        $dari = $grup->min('mulai')->copy();
        if ($dari->diffInDays($hariIni) >= self::MAKS_HARI) {
            $dari = $hariIni->copy()->subDays(self::MAKS_HARI - 1);
        }

        return new self('mt', $dari, $hariIni, 'MT '.['', 'I', 'II', 'III'][$mt].' '.$grup->first()->tahun);
    }
}
