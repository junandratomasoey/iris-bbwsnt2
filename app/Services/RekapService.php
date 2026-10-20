<?php

namespace App\Services;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use App\Models\WilayahSatgas;
use App\Support\Periode;
use App\Support\StatusKondisi as SK;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Rekap kondisi irigasi dalam satu periode.
 *
 * Status harian Sub-D.I. = status laporan yang masih berlaku hari itu (laporan ≤ 3 hari sebelumnya),
 * sama seperti tampilan "hari ini". Status harian D.I. = status Sub-D.I. terberat.
 * Luas (tanam/kering/puso/panen) memakai PUNCAK per Sub-D.I. lalu dijumlah antar Sub-D.I.,
 * karena luas yang sama dilaporkan ulang setiap hari dan tidak boleh dijumlah antar hari.
 */
class RekapService
{
    private const KODE = [0 => SK::KOSONG, 1 => SK::AMAN, 2 => SK::WASPADA, 3 => SK::SIAGA, 4 => SK::KRITIS];

    public function rekap(Periode $p, ?array $subIds = null): array
    {
        $kunci = 'rekap:v6:'.md5(json_encode($subIds)).':'.$p->dari->toDateString().':'.$p->sampai->toDateString();
        $ttl = $p->sampai->isToday() ? 300 : 3600;

        return Cache::remember($kunci, $ttl, fn () => $this->hitung($p, $subIds));
    }

    /**
     * Status D.I. untuk peta/strip sesuai mode.
     * terberat = status paling berat yang pernah terjadi; tersering = status yang paling banyak harinya (tanpa "belum ada laporan").
     */
    public static function statusMode(array $di, string $mode): string
    {
        if ($mode !== 'tersering') {
            return $di['terberat'];
        }

        return $di['tersering'];
    }

    private function hitung(Periode $p, ?array $subIds): array
    {
        $hari = [];
        for ($d = $p->dari->copy(); $d->lte($p->sampai); $d->addDay()) {
            $hari[] = $d->toDateString();
        }
        $n = count($hari);
        $indeks = array_flip($hari);

        $subs = SubDaerahIrigasi::query()->when($subIds !== null, fn ($q) => $q->whereIn('id', $subIds))
            ->get(['id', 'daerah_irigasi_id'])->pluck('daerah_irigasi_id', 'id');
        $diList = DaerahIrigasi::with('kabupaten.wilayahSatgas')
            ->when($subIds !== null, fn ($q) => $q->whereIn('id', $subs->unique()->values()))
            ->orderBy('kode')->get();

        // status harian per D.I. (kode 0–4) & statistik per Sub-D.I.
        $diHari = [];
        foreach ($diList as $di) {
            $diHari[$di->id] = array_fill(0, $n, 0);
        }
        $statSub = [];

        $subHari = []; // [sub][tanggal] = true — untuk kepatuhan (Sub-D.I. yang melapor per hari, berapa pun petaknya)
        $proses = function (int $subId, array $baris, int $petak = 0) use (&$diHari, &$statSub, &$subHari, $hari, $n, $subs, $p) {
            $diId = $subs[$subId] ?? null;
            if (! $diId || ! isset($diHari[$diId])) {
                return;
            }
            $st = ['tanam' => 0.0, 'padi' => 0.0, 'palawija' => 0.0, 'horti' => 0.0, 'kering' => 0.0, 'puso' => 0.0, 'panen' => 0.0, 'debit' => 0.0, 'debit_n' => 0, 'debit_min' => null, 'laporan' => 0];
            $dari = $p->dari->toDateString();
            foreach ($baris as $b) {
                if ($b['tanggal'] < $dari) {
                    continue; // laporan sebelum periode hanya dipakai untuk status hari pertama
                }
                $st['laporan']++;
                $subHari[$subId][$b['tanggal']] = true;
                $st['tanam'] = max($st['tanam'], $b['tanam']);
                foreach (['padi', 'palawija', 'horti'] as $k) {
                    $st[$k] = max($st[$k], $b[$k]);
                }
                $st['kering'] = max($st['kering'], $b['kering']);
                $st['puso'] = max($st['puso'], $b['puso']);
                $st['panen'] = max($st['panen'], $b['panen']);
                if ($b['debit'] !== null) { // laporan tanaman baru tidak berisi debit
                    $st['debit'] += $b['debit'];
                    $st['debit_n']++;
                    $st['debit_min'] = $st['debit_min'] === null ? $b['debit'] : min($st['debit_min'], $b['debit']);
                }
            }
            $statSub[] = $st + ['sub' => $subId, 'petak' => $petak]; // satu baris per unit laporan (Sub-D.I. atau petak tersier)

            // status tiap hari: laporan terakhir yang berumur ≤ MASA_BERLAKU_HARI
            $k = -1;
            $jumlah = count($baris);
            for ($i = 0; $i < $n; $i++) {
                while ($k + 1 < $jumlah && $baris[$k + 1]['tanggal'] <= $hari[$i]) {
                    $k++;
                }
                if ($k < 0) {
                    continue;
                }
                $umur = (strtotime($hari[$i]) - strtotime($baris[$k]['tanggal'])) / 86400;
                if ($umur < SK::MASA_BERLAKU_HARI && $baris[$k]['kode'] > $diHari[$diId][$i]) {
                    $diHari[$diId][$i] = $baris[$k]['kode'];
                }
            }
        };

        $cursor = LaporanHarian::query()->toBase()
            ->select(['sub_daerah_irigasi_id', 'petak_kunci', 'tanggal', 'kondisi_saluran', 'luas_tanam_ha', 'luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'luas_kering_ha', 'luas_puso_ha', 'luas_panen_ha', 'debit_intake_lps'])
            ->whereBetween('tanggal', [$p->dari->copy()->subDays(SK::MASA_BERLAKU_HARI - 1)->toDateString(), $p->sampai->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->orderBy('sub_daerah_irigasi_id')->orderBy('petak_kunci')->orderBy('tanggal')->cursor();

        // tiap unit (Sub-D.I. atau petak tersier) diproses sendiri; status D.I. = unit terberat, luas puncak dijumlah antar-unit
        $subKini = null;
        $petakKini = 0;
        $unitKini = null;
        $baris = [];
        foreach ($cursor as $r) {
            $unit = $r->sub_daerah_irigasi_id.':'.$r->petak_kunci;
            if ($unit !== $unitKini) {
                if ($subKini !== null) {
                    $proses($subKini, $baris, $petakKini);
                }
                $subKini = (int) $r->sub_daerah_irigasi_id;
                $petakKini = (int) $r->petak_kunci;
                $unitKini = $unit;
                $baris = [];
            }
            $tanam = (float) $r->luas_tanam_ha;
            $kering = (float) $r->luas_kering_ha;
            $puso = (float) $r->luas_puso_ha;
            $baris[] = [
                'tanggal' => substr((string) $r->tanggal, 0, 10),
                'kode' => SK::URUTAN[SK::dariNilai($r->kondisi_saluran, $tanam, $kering, $puso)],
                'tanam' => $tanam, 'kering' => $kering, 'puso' => $puso,
                'padi' => (float) $r->luas_padi_ha, 'palawija' => (float) $r->luas_palawija_ha, 'horti' => (float) $r->luas_horti_ha,
                'panen' => (float) $r->luas_panen_ha, 'debit' => $r->debit_intake_lps === null ? null : (float) $r->debit_intake_lps,
            ];
        }
        if ($subKini !== null) {
            $proses($subKini, $baris, $petakKini);
        }

        // ---------- laporan air Manager: kondisi saluran ikut status harian D.I.; debit dari intake & sungai ----------
        $statAir = [];
        $airPerDi = LaporanAir::query()->toBase()
            ->select(['daerah_irigasi_id', 'tanggal', 'kondisi_saluran', 'debit_intake_lps', 'debit_sungai_m3s'])
            ->whereBetween('tanggal', [$p->dari->copy()->subDays(SK::MASA_BERLAKU_HARI - 1)->toDateString(), $p->sampai->toDateString()])
            ->whereIn('daerah_irigasi_id', $diList->pluck('id'))
            ->orderBy('tanggal')->get()->groupBy('daerah_irigasi_id');
        foreach ($airPerDi as $diId => $daftar) {
            $baris = $daftar->map(fn ($r) => ['tanggal' => substr((string) $r->tanggal, 0, 10), 'kode' => SK::URUTAN[SK::dariSaluran($r->kondisi_saluran)]])->values()->all();
            $k = -1;
            for ($i = 0; $i < $n; $i++) {
                while ($k + 1 < count($baris) && $baris[$k + 1]['tanggal'] <= $hari[$i]) {
                    $k++;
                }
                if ($k >= 0 && (strtotime($hari[$i]) - strtotime($baris[$k]['tanggal'])) / 86400 < SK::MASA_BERLAKU_HARI && $baris[$k]['kode'] > $diHari[$diId][$i]) {
                    $diHari[$diId][$i] = $baris[$k]['kode'];
                }
            }
            $dalam = $daftar->filter(fn ($r) => substr((string) $r->tanggal, 0, 10) >= $p->dari->toDateString());
            $sungai = $dalam->pluck('debit_sungai_m3s')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v);
            $statAir[$diId] = [
                'n' => $dalam->count(),
                'intake_rata' => $dalam->isNotEmpty() ? round($dalam->avg(fn ($r) => (float) $r->debit_intake_lps), 1) : null,
                'intake_min' => $dalam->isNotEmpty() ? round($dalam->min(fn ($r) => (float) $r->debit_intake_lps), 1) : null,
                'sungai_rata' => $sungai->isNotEmpty() ? round($sungai->avg(), 3) : null,
                'sungai_min' => $sungai->isNotEmpty() ? round($sungai->min(), 3) : null,
            ];
        }

        // ---------- per D.I. ----------
        $subPerDi = $subs->countBy();
        $di = [];
        foreach ($diList as $d) {
            $hitung = array_fill_keys(array_values(self::KODE), 0);
            foreach ($diHari[$d->id] as $kode) {
                $hitung[self::KODE[$kode]]++;
            }
            $terberat = self::KODE[max($diHari[$d->id] ?: [0])];
            $tersering = SK::KOSONG;
            $maks = 0;
            foreach ([SK::KRITIS, SK::SIAGA, SK::WASPADA, SK::AMAN] as $s) { // seri → yang lebih berat
                if ($hitung[$s] > $maks) {
                    [$tersering, $maks] = [$s, $hitung[$s]];
                }
            }
            $ids = $subs->filter(fn ($v) => $v === $d->id)->keys()->all();
            $st = collect($statSub)->whereIn('sub', $ids);
            // Sub-D.I. yang sudah dilaporkan per petak: luas dari laporan tingkat Sub-D.I. lama tidak ikut dijumlah (hindari dobel)
            $berpetak = $st->where('petak', '>', 0)->pluck('sub')->unique()->all();
            $st = $st->reject(fn ($x) => $x['petak'] === 0 && in_array($x['sub'], $berpetak, true));
            $debitN = $st->sum('debit_n');
            $jumlahSub = $subPerDi[$d->id] ?? 0;
            $laporan = (int) $st->sum('laporan');
            $hariLapor = collect($ids)->sum(fn ($id) => count($subHari[$id] ?? []));

            $di[$d->id] = [
                'id' => $d->id, 'kode' => $d->kode, 'nama' => $d->nama,
                'kabupaten' => $d->kabupaten?->nama, 'satgas' => $d->kabupaten?->wilayahSatgas?->kode,
                'luas_ha' => (float) ($d->luas_fungsional_ha ?: $d->luas_permen_ha), 'lat' => $d->lat, 'lng' => $d->lng,
                'hari' => $hitung,
                'hari_berisiko' => $hitung[SK::SIAGA] + $hitung[SK::KRITIS],
                'terberat' => $terberat, 'tersering' => $tersering,
                'jumlah_sub' => $jumlahSub, 'laporan' => $laporan,
                'kepatuhan' => $jumlahSub ? (int) round($hariLapor / ($jumlahSub * $n) * 100) : 0,
                'puncak_tanam_ha' => round($st->sum('tanam'), 2),
                'puncak_padi_ha' => round($st->sum('padi'), 2), 'puncak_palawija_ha' => round($st->sum('palawija'), 2), 'puncak_horti_ha' => round($st->sum('horti'), 2),
                'puncak_kering_ha' => round($st->sum('kering'), 2),
                'puncak_puso_ha' => round($st->sum('puso'), 2), 'puncak_panen_ha' => round($st->sum('panen'), 2),
                // debit intake dari laporan air Manager; laporan lama (sebelum dipisah) sebagai cadangan
                'debit_rata_lps' => ($statAir[$d->id]['n'] ?? 0) ? $statAir[$d->id]['intake_rata'] : ($debitN ? round($st->sum('debit') / $debitN, 1) : null),
                'debit_min_lps' => ($statAir[$d->id]['n'] ?? 0) ? $statAir[$d->id]['intake_min'] : $st->whereNotNull('debit_min')->min('debit_min'),
                'debit_sungai_rata_m3s' => $statAir[$d->id]['sungai_rata'] ?? null,
                'debit_sungai_min_m3s' => $statAir[$d->id]['sungai_min'] ?? null,
                'laporan_air' => $statAir[$d->id]['n'] ?? 0,
            ];
        }

        // ---------- kalender & tren ----------
        $kolom = $this->kolom($hari, $p->mingguan());
        $kalender = [];
        foreach ($di as $id => $_) {
            $kalender[$id] = array_map(fn ($c) => self::KODE[max(array_slice($diHari[$id], $c['awal'], $c['panjang']) ?: [0])], $kolom);
        }
        $tren = array_map(function ($c, $i) use ($kalender) {
            $hit = array_fill_keys(array_values(self::KODE), 0);
            foreach ($kalender as $baris) {
                $hit[$baris[$i]]++;
            }

            return ['label' => $c['label'], 'judul' => $c['judul']] + $hit;
        }, $kolom, array_keys($kolom));

        // laporan per kolom (untuk garis "laporan masuk")
        $lapPerHari = LaporanHarian::query()->whereBetween('tanggal', [$p->dari->toDateString(), $p->sampai->toDateString()])
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->selectRaw('tanggal, count(*) as n')->groupBy('tanggal')->pluck('n', 'tanggal')
            ->mapWithKeys(fn ($v, $k) => [substr((string) $k, 0, 10) => (int) $v]);
        foreach ($kolom as $i => $c) {
            $tren[$i]['laporan'] = (int) collect(array_slice($hari, $c['awal'], $c['panjang']))->sum(fn ($h) => $lapPerHari[$h] ?? 0);
        }

        // ---------- kepatuhan per Satgas ----------
        $kepatuhan = $this->kepatuhan($diList, $subs, $subHari, $n);

        // ---------- kejadian baru dalam periode ----------
        $kejadian = Kejadian::whereBetween('tanggal_lapor', [$p->dari->toDateString(), $p->sampai->toDateString()])
            ->when($subIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('sub_daerah_irigasi_id')->orWhereIn('sub_daerah_irigasi_id', $subIds)))
            ->selectRaw('status_penanganan, count(*) as jumlah, sum(luas_terdampak_ha) as luas, sum(luas_tertangani_ha) as tertangani')
            ->groupBy('status_penanganan')->get()
            ->mapWithKeys(fn ($r) => [$r->status_penanganan => ['jumlah' => (int) $r->jumlah, 'luas' => (float) $r->luas, 'tertangani' => (float) $r->tertangani]])->all();

        $koleksi = collect($di);

        return [
            'dari' => $p->dari->toDateString(), 'sampai' => $p->sampai->toDateString(), 'jumlah_hari' => $n,
            'mingguan' => $p->mingguan(),
            'di' => $di,
            'kalender' => ['kolom' => array_map(fn ($c) => ['label' => $c['label'], 'judul' => $c['judul']], $kolom), 'baris' => $kalender],
            'tren' => $tren,
            'kepatuhan' => $kepatuhan,
            'kejadian' => $kejadian,
            'total' => [
                'jumlah_sub' => $subs->count(),
                'laporan' => (int) $koleksi->sum('laporan'),
                'kepatuhan' => $subs->count() ? (int) round($koleksi->sum('laporan') / ($subs->count() * $n) * 100) : 0,
                'hari_di' => collect(self::KODE)->mapWithKeys(fn ($s) => [$s => $koleksi->sum(fn ($d) => $d['hari'][$s])])->all(),
                'puncak_tanam_ha' => round($koleksi->sum('puncak_tanam_ha'), 1),
                'puncak_padi_ha' => round($koleksi->sum('puncak_padi_ha'), 1),
                'puncak_palawija_ha' => round($koleksi->sum('puncak_palawija_ha'), 1),
                'puncak_horti_ha' => round($koleksi->sum('puncak_horti_ha'), 1),
                'puncak_kering_ha' => round($koleksi->sum('puncak_kering_ha'), 1),
                'puncak_puso_ha' => round($koleksi->sum('puncak_puso_ha'), 1),
                'puncak_panen_ha' => round($koleksi->sum('puncak_panen_ha'), 1),
            ],
        ];
    }

    /** Kolom kalender: per hari, atau per minggu (Senin–Minggu) untuk rentang panjang. */
    private function kolom(array $hari, bool $mingguan): array
    {
        if (! $mingguan) {
            return array_map(fn ($h, $i) => [
                'awal' => $i, 'panjang' => 1,
                'label' => Carbon::parse($h)->format('j'),
                'judul' => Carbon::parse($h)->translatedFormat('D, d M Y'),
            ], $hari, array_keys($hari));
        }
        $kolom = [];
        foreach ($hari as $i => $h) {
            $t = Carbon::parse($h);
            if ($i === 0 || $t->isMonday()) {
                $kolom[] = ['awal' => $i, 'panjang' => 0, 'mulai' => $t->copy()];
            }
            $kolom[count($kolom) - 1]['panjang']++;
        }

        $bulanLalu = null;

        return array_map(function ($c) use (&$bulanLalu) {
            $akhir = $c['mulai']->copy()->addDays($c['panjang'] - 1);
            $bulan = $akhir->format('Y-m'); // minggu milik bulan tempat ia berakhir
            $label = $bulan !== $bulanLalu ? $akhir->translatedFormat('M') : '';
            $bulanLalu = $bulan;

            return [
                'awal' => $c['awal'], 'panjang' => $c['panjang'],
                'label' => $label,
                'judul' => $c['mulai']->translatedFormat('d M').' – '.$akhir->translatedFormat('d M Y'),
            ];
        }, $kolom);
    }

    /** @param array $subHari [sub][tanggal] = true */
    private function kepatuhan($diList, $subs, array $subHari, int $n): array
    {
        $satgasDi = $diList->mapWithKeys(fn ($d) => [$d->id => $d->kabupaten?->wilayah_satgas_id]);
        $baris = WilayahSatgas::orderBy('id')->get()->map(fn ($w) => ['id' => $w->id, 'kode' => $w->kode, 'cakupan' => $w->cakupan])->push(['id' => null, 'kode' => null, 'cakupan' => null]);

        return $baris->map(function ($w) use ($satgasDi, $subs, $subHari, $n) {
            $ids = $subs->filter(fn ($diId) => ($satgasDi[$diId] ?? null) === $w['id'])->keys();
            $laporan = $ids->sum(fn ($id) => count($subHari[$id] ?? []));
            $kab = Kabupaten::where('wilayah_satgas_id', $w['id'])->whereHas('daerahIrigasi')->orderBy('nama')->pluck('nama')->implode(', ');

            return $w + ['kabupaten' => $kab, 'jumlah_sub' => $ids->count(), 'laporan' => (int) $laporan,
                'persen' => $ids->count() ? (int) round($laporan / ($ids->count() * $n) * 100) : 0];
        })->filter(fn ($w) => $w['jumlah_sub'] > 0)->values()->all();
    }
}
