<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
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
    /** @return Collection<int, LaporanHarian> laporan terakhir per sub (key = sub id) */
    public function laporanTerkini(Carbon $tanggal, ?array $subIds = null): Collection
    {
        $dari = $tanggal->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString();

        return LaporanHarian::query()
            ->whereBetween('tanggal', [$dari, $tanggal->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->orderBy('tanggal')->orderBy('id')
            ->get()
            ->keyBy('sub_daerah_irigasi_id'); // yang terakhir menimpa → paling baru
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

        return $diList->mapWithKeys(function (DaerahIrigasi $di) use ($subs, $laporan, $tanggal) {
            $daftar = $subs->get($di->id, collect());
            $status = StatusKondisi::KOSONG;
            $agg = ['tanam' => 0, 'kering' => 0, 'puso' => 0, 'panen' => 0, 'debit' => 0, 'lapor' => 0];
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
                $agg['kering'] += (float) $l->luas_kering_ha;
                $agg['puso'] += (float) $l->luas_puso_ha;
                $agg['panen'] += (float) $l->luas_panen_ha;
                $agg['debit'] += (float) $l->debit_intake_lps;
                $terakhir = max($terakhir, $l->tanggal->toDateString());
            }
            $mt = $di->mtBerjalan($tanggal);

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
                'luas_kering_ha' => round($agg['kering'], 2),
                'luas_puso_ha' => round($agg['puso'], 2),
                'luas_panen_ha' => round($agg['panen'], 2),
                'debit_total_lps' => round($agg['debit'], 1),
                'laporan_terakhir' => $terakhir,
                'mt' => $mt?->label,
            ]];
        });
    }
}
