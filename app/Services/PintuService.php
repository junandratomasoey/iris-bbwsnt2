<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use Illuminate\Support\Collection;

/** Ringkasan pemantauan pintu air: pembacaan terakhir, perubahan dari pembacaan sebelumnya, dan status terhadap rentang normal. */
class PintuService
{
    /**
     * Pintu aktif dalam jangkauan beserta pembacaan terakhir & sebelumnya.
     *
     * @return Collection<int, PintuAir> dengan atribut tambahan: terakhir, sebelum, status_debit, status_tma, minggu_ini, riwayat
     */
    public function daftar(?array $subIds, ?int $diId = null, int $riwayat = 8): Collection
    {
        $pintu = PintuAir::with('subDaerahIrigasi:id,nama', 'daerahIrigasi:id,nama')
            ->terjangkau($subIds)->where('aktif', true)
            ->when($diId, fn ($q) => $q->where('daerah_irigasi_id', $diId))
            ->urut()->get();
        if ($pintu->isEmpty()) {
            return $pintu;
        }

        // pembacaan terbaru per pintu (maks $riwayat), diurutkan terbaru dulu
        $bacaan = PembacaanPintu::with('sesi:id,jenis,tanggal')
            ->whereIn('pintu_air_id', $pintu->pluck('id'))
            ->where('tanggal', '>=', today()->subDays(180)->toDateString())
            ->orderByDesc('tanggal')->orderByDesc('id')->get()
            ->groupBy('pintu_air_id');
        $senin = SesiPintu::seninIni();

        return $pintu->each(function (PintuAir $p) use ($bacaan, $riwayat, $senin) {
            $r = ($bacaan[$p->id] ?? collect())->take($riwayat)->values();
            $p->setAttribute('riwayat', $r->reverse()->values());
            $p->setAttribute('terakhir', $r->get(0));
            $p->setAttribute('sebelum', $r->get(1));
            $p->setAttribute('status_debit', $p->statusDebit($r->get(0)?->debit_lps));
            $p->setAttribute('status_tma', $p->statusTma($r->get(0)?->tma_cm));
            $p->setAttribute('minggu_ini', $r->get(0) && $r->get(0)->tanggal->gte($senin));
        });
    }

    /** Hitungan untuk kartu ringkasan. */
    public function hitung(Collection $pintu): array
    {
        return [
            'total' => $pintu->count(),
            'dibaca' => $pintu->where('minggu_ini', true)->count(),
            'belum' => $pintu->where('minggu_ini', false)->count(),
            'kurang' => $pintu->filter(fn ($p) => $p->status_debit === 'kurang' || $p->status_tma === 'rendah')->count(),
            'lebih' => $pintu->filter(fn ($p) => $p->status_debit === 'berlebih' || $p->status_tma === 'tinggi')->count(),
            'tanpa_rentang' => $pintu->filter(fn ($p) => ! $p->punyaRentangDebit() && $p->tma_min_cm === null && $p->tma_maks_cm === null)->count(),
        ];
    }

    /** Ringkasan per D.I. (tampilan awal tanpa filter D.I.). */
    public function perDi(Collection $pintu): Collection
    {
        return $pintu->groupBy('daerah_irigasi_id')->map(fn ($g) => [
            'di' => $g->first()->daerahIrigasi,
            'terakhir' => $g->pluck('terakhir')->filter()->max(fn ($b) => $b->tanggal),
        ] + $this->hitung($g))->sortBy(fn ($r) => $r['di']->nama)->values();
    }

    /** D.I. dalam jangkauan yang bisa dipilih (beserta jumlah pintu). */
    public function diList(?array $diIds): Collection
    {
        return DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))
            ->withCount(['pintuAir' => fn ($q) => $q->where('aktif', true)])->orderBy('nama')->get(['id', 'nama']);
    }

    /** Selisih debit terhadap pembacaan sebelumnya: [selisih, persen] atau null. */
    public static function perubahan(?PembacaanPintu $kini, ?PembacaanPintu $lalu, string $kolom = 'debit_lps'): ?array
    {
        if (! $kini || ! $lalu || $kini->$kolom === null || $lalu->$kolom === null) {
            return null;
        }
        $d = $kini->$kolom - $lalu->$kolom;

        return [$d, $lalu->$kolom > 0 ? $d / $lalu->$kolom * 100 : null];
    }
}
