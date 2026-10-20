<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\LaporanHarian;
use App\Models\User;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Ringkasan pemantauan pintu air: pembacaan terakhir, perubahan dari pembacaan sebelumnya, dan status terhadap rentang normal. */
class PintuService
{
    /**
     * Pintu aktif dalam jangkauan beserta pembacaan terakhir & sebelumnya.
     *
     * @return Collection<int, PintuAir> dengan atribut tambahan: terakhir, sebelum, status_debit, status_tma, minggu_ini, riwayat
     */
    public function daftar(?array $subIds, ?int $diId = null, int $riwayat = 8, ?array $hanyaId = null): Collection
    {
        $pintu = PintuAir::with('subDaerahIrigasi:id,nama', 'daerahIrigasi:id,nama', 'hulu:id,kode,lat,lng', 'petugas:id,name')
            ->terjangkau($subIds)->where('aktif', true)
            ->when($diId, fn ($q) => $q->where('daerah_irigasi_id', $diId))
            ->when($hanyaId !== null, fn ($q) => $q->whereIn('id', $hanyaId))
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

    /**
     * Pintu yang boleh diisi pembacaannya oleh user:
     * pengelola pintu → semua pintu di D.I. jangkauannya; pintu yang ditugaskan kepadanya;
     * pintu yang belum ditugaskan ke siapa pun di wilayah isiannya.
     */
    public function bolehIsi(User $u, ?int $diId = null): Builder
    {
        $q = PintuAir::query()->when($diId, fn ($x) => $x->where('daerah_irigasi_id', $diId));
        if (! $u->boleh('isi-pintu')) {
            return $q->whereRaw('1 = 0');
        }
        $subIds = $u->subIdsPelaporan('isi-pintu');

        return $q->where(function ($w) use ($u, $subIds) {
            if ($u->boleh('kelola-pintu')) {
                $diIds = $u->diIdsTerjangkau();
                $w->orWhere(fn ($x) => $diIds === null ? $x->whereNotNull('pintu_air.id') : $x->whereIn('daerah_irigasi_id', $diIds));
            }
            $w->orWhereHas('petugas', fn ($x) => $x->where('users.id', $u->id));
            $w->orWhere(fn ($x) => $x->terjangkau($subIds)->whereDoesntHave('petugas'));
        });
    }

    /** Boleh menyimpan titik koordinat pintu ini dari lapangan. */
    public function bolehTitik(User $u, PintuAir $p): bool
    {
        return ! $p->lat || $u->bolehKelolaPintu($p->daerah_irigasi_id)
            || $p->petugas()->where('users.id', $u->id)->exists();
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

    /**
     * Neraca air & pemerataan satu D.I. untuk satu minggu (Senin s.d. Minggu, atau s.d. hari ini).
     * Neraca: debit pintu hulu dibanding jumlah debit pintu tepat di bawahnya (pembacaan terakhir minggu itu).
     * Pemerataan: debit per hektar (L/dt/Ha) tiap petak tersier dibanding rata-rata D.I.
     * Tidak ada ambang "wajar/tidak": angka ditampilkan apa adanya; toleransi ditetapkan Bidang OP.
     */
    public function neraca(int $diId, Carbon $senin, ?array $subIds = null): array
    {
        $akhir = $senin->copy()->addDays(6)->min(today());
        $pintu = PintuAir::with('subDaerahIrigasi:id,nama', 'petugas:id,name')->where('daerah_irigasi_id', $diId)->where('aktif', true)
            ->terjangkau($subIds)->urut()->get()->keyBy('id');
        $baca = PembacaanPintu::whereIn('pintu_air_id', $pintu->keys())
            ->whereBetween('tanggal', [$senin->toDateString(), $akhir->toDateString()])
            ->orderBy('tanggal')->orderBy('id')->get()->keyBy('pintu_air_id'); // terakhir menimpa
        $debit = fn ($id) => $baca->get($id)?->debit_lps;

        $neraca = [];
        foreach ($pintu as $p) {
            $anak = $pintu->where('hulu_id', $p->id)->values();
            if ($anak->isEmpty()) {
                continue;
            }
            $hulu = $debit($p->id);
            $terbaca = $anak->filter(fn ($a) => $debit($a->id) !== null);
            $jumlah = $terbaca->sum(fn ($a) => $debit($a->id));
            $selisih = $hulu !== null && $terbaca->isNotEmpty() ? $hulu - $jumlah : null;
            $neraca[] = [
                'pintu' => $p, 'debit' => $hulu, 'tanggal' => $baca->get($p->id)?->tanggal,
                'anak' => $anak->map(fn ($a) => ['pintu' => $a, 'debit' => $debit($a->id), 'tanggal' => $baca->get($a->id)?->tanggal])->all(),
                'jumlah_anak' => $terbaca->isNotEmpty() ? round($jumlah, 2) : null,
                'terbaca' => $terbaca->count(), 'total' => $anak->count(),
                'selisih' => $selisih !== null ? round($selisih, 2) : null,
                'persen' => $selisih !== null && $hulu > 0 ? round($selisih / $hulu * 100, 1) : null,
                'lengkap' => $hulu !== null && $terbaca->count() === $anak->count(),
            ];
        }

        $tersier = $pintu->where('tingkat', 'tersier')->values();
        $laporan = LaporanHarian::whereIn('pintu_air_id', $tersier->pluck('id'))
            ->whereBetween('tanggal', [$senin->toDateString(), $akhir->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->orderBy('tanggal')->orderBy('id')->get()->keyBy('pintu_air_id');
        $berdata = $tersier->filter(fn ($p) => $p->luas_layanan_ha > 0 && $debit($p->id) !== null);
        $luas = $berdata->sum('luas_layanan_ha');
        $rata = $luas > 0 ? $berdata->sum(fn ($p) => $debit($p->id)) / $luas : null;
        $merata = $tersier->map(function ($p) use ($debit, $rata, $laporan, $baca) {
            $d = $debit($p->id);
            $q = $d !== null && $p->luas_layanan_ha > 0 ? $d / $p->luas_layanan_ha : null;
            $l = $laporan->get($p->id);

            return [
                'pintu' => $p, 'debit' => $d, 'tanggal' => $baca->get($p->id)?->tanggal, 'luas' => $p->luas_layanan_ha,
                'per_ha' => $q !== null ? round($q, 3) : null,
                'rencana' => $p->debit_rencana_lps,
                'persen_rencana' => $d !== null && $p->debit_rencana_lps > 0 ? (int) round($d / $p->debit_rencana_lps * 100) : null,
                'rasio' => $q !== null && $rata ? (int) round($q / $rata * 100) : null,
                'status_debit' => $p->statusDebit($d),
                'laporan' => $l, 'status_lahan' => $l ? StatusKondisi::dariLaporan($l) : null,
            ];
        })->sortBy(fn ($r) => [$r['rasio'] === null ? 1 : 0, $r['rasio'] ?? 0])->values()->all();

        return [
            'senin' => $senin, 'akhir' => $akhir, 'neraca' => $neraca, 'merata' => $merata,
            'rata_per_ha' => $rata !== null ? round($rata, 3) : null,
            'dibaca' => $baca->count(), 'total' => $pintu->count(),
        ];
    }

    /** Titik pintu untuk peta (pintu aktif yang sudah punya koordinat) beserta garis ke pintu hulunya. */
    public function peta(int $diId, ?array $subIds): array
    {
        return $this->daftar($subIds, $diId, 1)->filter(fn ($p) => $p->lat && $p->lng)->map(fn (PintuAir $p) => [
            'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'tingkat' => $p->tingkat, 'tingkat_label' => $p->tingkat_label,
            'lat' => $p->lat, 'lng' => $p->lng,
            'hulu' => $p->hulu?->lat ? [$p->hulu->lat, $p->hulu->lng] : null,
            'debit' => $p->terakhir?->debit_lps, 'tma' => $p->terakhir?->tma_cm,
            'tanggal' => $p->terakhir?->tanggal->translatedFormat('d M Y'),
            'status_debit' => $p->status_debit, 'status_tma' => $p->status_tma,
            'petugas' => $p->petugas->pluck('name')->implode(', '),
            'url' => route('pintu.show', $p),
        ])->values()->all();
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
