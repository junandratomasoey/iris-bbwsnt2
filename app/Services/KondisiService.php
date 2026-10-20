<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Menghitung kondisi terkini per Sub-D.I. dan per D.I. dari laporan harian.
 * Laporan dianggap berlaku selama StatusKondisi::MASA_BERLAKU_HARI hari.
 */
class KondisiService
{
    private const URUT_SALURAN = ['penuh' => 0, 'normal' => 1, 'kurang' => 2, 'kering' => 3];

    /**
     * Laporan terkini per Sub-D.I. (key = sub id). Bila Sub-D.I. dilaporkan per petak tersier, laporan terakhir
     * tiap petak digabung: luas & debit dijumlah, kondisi saluran & status diambil yang terberat.
     * Laporan tingkat Sub-D.I. hanya dipakai bila belum ada laporan per petak.
     *
     * @return Collection<int, LaporanHarian>
     */
    public function laporanTerkini(Carbon $tanggal, ?array $subIds = null): Collection
    {
        $dari = $tanggal->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString();

        return LaporanHarian::query()
            ->with('petak:id,kode,nama,luas_layanan_ha')
            ->whereBetween('tanggal', [$dari, $tanggal->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->orderBy('tanggal')->orderBy('id')
            ->get()
            ->groupBy('sub_daerah_irigasi_id')
            ->map(fn ($daftar) => $this->gabung($daftar->keyBy('petak_kunci'))); // yang terakhir menimpa → paling baru per petak
    }

    /** @param Collection<int, LaporanHarian> $unit laporan terakhir per petak (key 0 = tingkat Sub-D.I.) */
    public function gabung(Collection $unit): LaporanHarian
    {
        if ($unit->keys()->contains(fn ($k) => $k > 0)) {
            $unit = $unit->filter(fn ($l, $k) => $k > 0); // (Eloquent except() memakai id model, bukan kunci)
        }
        $terbaru = $unit->sortBy(fn ($l) => [$l->tanggal->toDateString(), $l->id])->last();
        if ($unit->count() === 1) {
            if ($terbaru->pintu_air_id) {
                $terbaru->setAttribute('petak_rinci', [$this->rinci($terbaru)]);
            }

            return $terbaru;
        }

        $g = clone $terbaru;
        foreach (['luas_tanam_ha', 'luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha', 'luas_puso_ha'] as $k) {
            $g->setAttribute($k, round($unit->sum(fn ($l) => (float) $l->$k), 2));
        }
        // data air hanya ada di laporan lama (sebelum laporan air dipisah ke Manager)
        $berair = $unit->filter(fn ($l) => $l->debit_intake_lps !== null);
        $g->setAttribute('debit_intake_lps', $berair->isNotEmpty() ? round($berair->sum(fn ($l) => (float) $l->debit_intake_lps), 2) : null);
        $g->setAttribute('tma_cm', $berair->isNotEmpty() ? round($berair->avg(fn ($l) => (float) $l->tma_cm), 1) : null);
        $g->setAttribute('kondisi_saluran', $unit->pluck('kondisi_saluran')->sortBy(fn ($k) => self::URUT_SALURAN[$k] ?? -1)->last());
        $g->setAttribute('jenis_palawija', $unit->pluck('jenis_palawija')->filter()->flatMap(fn ($j) => array_map('trim', explode(',', $j)))->unique()->implode(', ') ?: null);
        $g->setAttribute('jenis_horti', $unit->pluck('jenis_horti')->filter()->flatMap(fn ($j) => array_map('trim', explode(',', $j)))->unique()->implode(', ') ?: null);
        $g->setAttribute('status_gabungan', $unit->reduce(fn ($s, $l) => StatusKondisi::maks($s, StatusKondisi::dariLaporan($l)), StatusKondisi::KOSONG));
        $g->setAttribute('petak_rinci', $unit->sortBy(fn ($l) => $l->petak?->kode)->map(fn ($l) => $this->rinci($l))->values()->all());
        $g->setRelation('petak', null);

        return $g;
    }

    private function rinci(LaporanHarian $l): array
    {
        $st = StatusKondisi::dariLaporan($l);

        return [
            'laporan_id' => $l->id, 'kode' => $l->petak?->kode, 'nama' => $l->petak?->nama, 'tanggal' => $l->tanggal->toDateString(),
            'status' => $st, 'tanam' => (float) $l->luas_tanam_ha, 'kering' => (float) $l->luas_kering_ha, 'puso' => (float) $l->luas_puso_ha,
            'kondisi' => $l->kondisi_saluran, 'debit' => $l->debit_intake_lps === null ? null : (float) $l->debit_intake_lps,
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
                    'debit_sungai_m3s' => $air->debit_sungai_m3s, 'tma_sungai_cm' => $air->tma_sungai_cm,
                    'debit_intake_lps' => $air->debit_intake_lps, 'tma_intake_cm' => $air->tma_intake_cm, 'id' => $air->id,
                ] : null,
                'laporan_terakhir' => $terakhir,
                'mt' => $mt?->label,
            ]];
        });
    }
}
