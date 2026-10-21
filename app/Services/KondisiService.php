<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\LaporanPetak;
use App\Models\SubDaerahIrigasi;
use App\Support\KodeIsian;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menghitung kondisi terkini per Sub-D.I. dan per D.I. dari laporan harian.
 * Laporan dianggap berlaku selama StatusKondisi::MASA_BERLAKU_HARI hari.
 */
class KondisiService
{
    /**
     * Laporan terkini per Sub-D.I. (key = sub id). Kondisi tiap petak tersier diambil dari laporan PPA terakhir
     * yang memuat petak itu (masih berlaku), lalu digabung: luas dijumlah, status = petak terberat.
     * Baris "seluruh Sub-D.I." (sebelum ada petak) hanya dipakai bila belum ada laporan per petak.
     *
     * @return Collection<int, LaporanHarian>
     */
    public function laporanTerkini(Carbon $tanggal, ?array $subIds = null): Collection
    {
        $dari = $tanggal->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString();

        return LaporanHarian::query()
            ->with('petak.pintu:id,kode,nama,luas_layanan_ha')
            ->whereBetween('tanggal', [$dari, $tanggal->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->orderBy('tanggal')->orderBy('id')
            ->get()
            ->groupBy('sub_daerah_irigasi_id')
            ->map(fn ($daftar) => $this->gabung($daftar));
    }

    /** @param Collection<int, LaporanHarian> $laporan laporan satu Sub-D.I., urut lama → baru */
    public function gabung(Collection $laporan): LaporanHarian
    {
        $unit = [];
        foreach ($laporan->sortBy(fn ($l) => [$l->tanggal->toDateString(), $l->id]) as $l) {
            foreach ($l->petak as $b) {
                $unit[(int) $b->pintu_air_id] = ['baris' => $b, 'laporan' => $l]; // yang terakhir menimpa → paling baru per petak
            }
        }
        $unit = collect($unit);
        if ($unit->keys()->contains(fn ($k) => $k > 0)) {
            $unit = $unit->filter(fn ($u, $k) => $k > 0);
        }
        $terbaru = $laporan->sortBy(fn ($l) => [$l->tanggal->toDateString(), $l->id])->last();
        if ($unit->isEmpty()) {
            return $terbaru;
        }

        $g = clone $terbaru;
        $baris = $unit->pluck('baris');
        $tanam = $baris->reject->bera();
        $jumlah = fn ($kumpulan, $k) => round($kumpulan->sum(fn ($b) => (float) $b->$k), 2);
        $panen = round($tanam->sum(fn ($b) => $b->fase === 6 ? (float) $b->luas_tanam_ha : (float) $b->luas_panen_ha), 2);
        $palawija = $tanam->whereIn('jenis_tanaman', ['J', 'K', 'Pl']);
        $g->setAttribute('luas_tanam_ha', $jumlah($tanam, 'luas_tanam_ha'));
        $g->setAttribute('luas_padi_ha', $jumlah($tanam->where('jenis_tanaman', 'P'), 'luas_tanam_ha'));
        $g->setAttribute('luas_palawija_ha', $jumlah($palawija, 'luas_tanam_ha'));
        $g->setAttribute('luas_horti_ha', 0);
        $g->setAttribute('jenis_palawija', $palawija->pluck('jenis_tanaman')->unique()->map(fn ($k) => KodeIsian::JENIS_TANAMAN[$k])->implode(', ') ?: null);
        $g->setAttribute('jenis_horti', null);
        $g->setAttribute('luas_kering_ha', $jumlah($baris, 'luas_kering_ha'));
        $g->setAttribute('luas_puso_ha', $jumlah($baris, 'luas_puso_ha'));
        $g->setAttribute('luas_panen_ha', $panen);
        $g->setAttribute('luas_hijau_ha', max(0, round($jumlah($tanam, 'luas_tanam_ha') - $jumlah($baris, 'luas_kering_ha') - $panen, 2)));
        $g->setAttribute('status_gabungan', $baris->reduce(fn ($s, $b) => StatusKondisi::maks($s, $b->status ?? $b->hitungStatus()), StatusKondisi::KOSONG));
        $g->setAttribute('kekurangan_air', $baris->whereIn('kecukupan_air', ['KR', 'KS', 'KB'])->count());
        $g->setAttribute('kelebihan_air', $baris->where('kecukupan_air', 'Bj')->count());
        $g->setAttribute('petak_rinci', $unit->sortBy(fn ($u) => $u['baris']->pintu?->kode)->map(fn ($u) => $this->rinci($u['baris'], $u['laporan']))->values()->all());
        $g->setRelation('petak', $baris->values());

        return $g;
    }

    private function rinci(LaporanPetak $b, LaporanHarian $l): array
    {
        $st = $b->status ?? $b->hitungStatus();

        return [
            'laporan_id' => $l->id, 'kode' => $b->pintu?->kode, 'nama' => $b->pintu?->nama ?? ($b->pintu_air_id ? null : 'Seluruh Sub-D.I.'),
            'tanggal' => $l->tanggal->toDateString(), 'status' => $st,
            'tanam' => (float) $b->luas_tanam_ha, 'kering' => (float) $b->luas_kering_ha, 'puso' => (float) $b->luas_puso_ha,
            'jenis' => KodeIsian::JENIS_TANAMAN[$b->jenis_tanaman] ?? null, 'fase' => KodeIsian::FASE[$b->fase] ?? null,
            'umur' => $b->umur_hst, 'kecukupan' => $b->kecukupan_air, 'kecukupan_label' => KodeIsian::KECUKUPAN_AIR[$b->kecukupan_air] ?? null,
            'kondisi' => null, 'debit' => null,
        ];
    }

    /**
     * Laporan air (Manager) terakhir per D.I. yang masih berlaku (key = D.I. id).
     *
     * @return Collection<int, LaporanAir>
     */
    public function airTerkini(Carbon $tanggal, ?array $diIds = null): Collection
    {
        $dari = $tanggal->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString();

        return LaporanAir::query()->whereBetween('tanggal', [$dari, $tanggal->toDateString()])
            ->when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))
            ->orderBy('tanggal')->orderBy('id')->get()->keyBy('daerah_irigasi_id');
    }

    /**
     * Ringkasan per D.I.
     *
     * @return Collection<int, array> key = DI id
     */
    public function ringkasanDi(Carbon $tanggal, ?array $subIds = null): Collection
    {
        $laporan = $this->laporanTerkini($tanggal, $subIds);
        $subs = SubDaerahIrigasi::query()
            ->when($subIds !== null, fn ($q) => $q->whereIn('id', $subIds))
            ->get(['id', 'daerah_irigasi_id', 'luas_ha'])
            ->groupBy('daerah_irigasi_id');

        $diList = DaerahIrigasi::with(['kabupaten.wilayahSatgas', 'jadwalTanam'])
            ->when($subIds !== null, fn ($q) => $q->whereIn('id', $subs->keys()))
            ->orderBy('kode')->get();
        $airSemua = $this->airTerkini($tanggal, $subIds !== null ? $subs->keys()->all() : null);

        return $diList->mapWithKeys(function (DaerahIrigasi $di) use ($subs, $laporan, $tanggal, $airSemua) {
            $daftar = $subs->get($di->id, collect());
            $status = StatusKondisi::KOSONG;
            $agg = ['tanam' => 0, 'kering' => 0, 'puso' => 0, 'panen' => 0, 'debit' => 0, 'lapor' => 0, 'padi' => 0, 'palawija' => 0, 'horti' => 0];
            $terakhir = null;
            $hitung = array_fill_keys(array_keys(StatusKondisi::URUTAN), 0);
            foreach ($daftar as $s) {
                $l = $laporan->get($s->id);
                $st = StatusKondisi::dariLaporan($l);
                $hitung[$st]++;
                if (! $l) {
                    continue;
                }
                $status = StatusKondisi::maks($status, $st);
                $agg['lapor']++;
                $agg['tanam'] += (float) $l->luas_tanam_ha;
                $agg['padi'] += (float) $l->luas_padi_ha;
                $agg['palawija'] += (float) $l->luas_palawija_ha;
                $agg['horti'] += (float) $l->luas_horti_ha;
                $agg['kering'] += (float) $l->luas_kering_ha;
                $agg['puso'] += (float) $l->luas_puso_ha;
                $agg['panen'] += (float) $l->luas_panen_ha;
                $agg['debit'] += (float) $l->debit_intake_lps;
                $terakhir = max($terakhir, $l->tanggal->toDateString());
            }
            $mt = $di->mtBerjalan($tanggal);
            // kondisi saluran dari laporan air Manager ikut menentukan status D.I.
            $air = $airSemua->get($di->id);
            if ($air) {
                $status = StatusKondisi::maks($status, $air->status());
                $terakhir = max($terakhir, $air->tanggal->toDateString());
            }

            return [$di->id => [
                'id' => $di->id,
                'kode' => $di->kode,
                'nama' => $di->nama,
                'kabupaten' => $di->kabupaten?->nama,
                'satgas' => $di->kabupaten?->wilayahSatgas?->kode,
                'luas_ha' => (float) ($di->luas_fungsional_ha ?: $di->luas_permen_ha),
                'luas_permen_ha' => (float) $di->luas_permen_ha,
                'lat' => $di->lat,
                'lng' => $di->lng,
                'status' => $status,
                'status_label' => StatusKondisi::label($status),
                'warna' => StatusKondisi::WARNA[$status],
                'jumlah_sub' => $daftar->count(),
                'sub_lapor' => $agg['lapor'],
                'sub_per_status' => $hitung,
                'luas_tanam_ha' => round($agg['tanam'], 2),
                'luas_padi_ha' => round($agg['padi'], 2),
                'luas_palawija_ha' => round($agg['palawija'], 2),
                'luas_horti_ha' => round($agg['horti'], 2),
                'luas_kering_ha' => round($agg['kering'], 2),
                'luas_puso_ha' => round($agg['puso'], 2),
                'luas_panen_ha' => round($agg['panen'], 2),
                'debit_total_lps' => $air ? round($air->debit_intake_lps, 1) : round($agg['debit'], 1),
                'air' => $air ? [
                    'tanggal' => $air->tanggal->toDateString(), 'kondisi' => $air->kondisi_saluran, 'status' => $air->status(),
                    'pemberian' => $air->status_pemberian, 'tma_hulu_cm' => $air->tma_sungai_cm,
                    'debit_sungai_m3s' => $air->debit_sungai_m3s, 'tma_sungai_cm' => $air->tma_sungai_cm,
                    'debit_intake_lps' => $air->debit_intake_lps, 'tma_intake_cm' => $air->tma_intake_cm, 'id' => $air->id,
                ] : null,
                'laporan_terakhir' => $terakhir,
                'mt' => $mt?->label,
            ]];
        });
    }
}
