<?php

namespace App\Services;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\Kejadian;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\P3a;
use App\Models\PintuAir;
use App\Models\SubDaerahIrigasi;
use App\Support\KodeIsian;
use App\Support\Periode;
use App\Support\StatusKondisi;
use Carbon\Carbon;

/**
 * Isi panel detail D.I. di peta: profil, jadwal MT, kondisi Sub-D.I., riwayat status,
 * foto laporan per tanggal, infrastruktur, dan kejadian.
 * Versi publik tanpa nomor HP, nama petugas, debit/TMA, dan tautan internal.
 */
class DetailDiService
{
    /** Panjang jendela foto per halaman & riwayat status (hari). */
    public const HARI = 30;

    private const TENAGA = ['pengamat' => 'Pengamat', 'juru' => 'Juru', 'manajer' => 'Manajer', 'ppa' => 'PPA', 'pob' => 'POB'];

    public function __construct(private KondisiService $kondisi) {}

    public function data(DaerahIrigasi $di, Periode|Carbon $periode, bool $internal, ?array $subIds = null): array
    {
        // Carbon = kompatibel lama: riwayat 30 hari s.d. tanggal itu
        $p = $periode instanceof Periode ? $periode : null;
        $tgl = $p ? $p->sampai->copy() : $periode;
        $dariRiwayat = $p && $p->rekap() ? $p->dari->copy() : $tgl->copy()->subDays(self::HARI - 1);
        $di->load(['fotoProfil', 'kabupaten.wilayahSatgas', 'jadwalTanam', 'subDaerahIrigasi' => fn ($q) => $q->when($subIds !== null, fn ($w) => $w->whereIn('id', $subIds))]);
        $subs = $di->subDaerahIrigasi;
        if ($internal) {
            $subs->load('petugas:id,name,no_hp,jabatan');
        }
        $subs->load(['fotoProfil', 'p3a:id,nama,sub_daerah_irigasi_id']);
        $ids = $subs->pluck('id')->all();
        $terkini = $this->kondisi->laporanTerkini($tgl, $ids);
        $ring = $this->kondisi->ringkasanDi($tgl, $subIds)->get($di->id);
        $bangunan = $this->bangunan($di, $internal);
        $pintu = PintuAir::where('daerah_irigasi_id', $di->id)->where('aktif', true)
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $ids))
            ->get(['id', 'sub_daerah_irigasi_id', 'tingkat', 'luas_layanan_ha', 'lat', 'lng']);
        $fotoLaporan = $this->fotoLaporanTerakhir($ids, $tgl, $internal);
        $konteks = ['bangunan' => collect($bangunan)->groupBy('sub_id'), 'pintu' => $pintu->groupBy('sub_daerah_irigasi_id'), 'foto' => $fotoLaporan];

        return [
            'tanggal' => $tgl->toDateString(),
            'di' => [
                'id' => $di->id, 'kode' => $di->kode, 'nama' => $di->nama,
                'kabupaten' => $di->kabupaten?->nama, 'satgas' => $di->kabupaten?->wilayahSatgas?->kode,
                'kewenangan' => $di->kewenangan, 'keterangan' => $di->keterangan, 'sumber_air' => $di->sumber_air,
                'luas' => [
                    'permen' => (float) $di->luas_permen_ha, 'baku' => (float) $di->luas_baku_ha,
                    'fungsional' => (float) $di->luas_fungsional_ha, 'potensial' => (float) $di->luas_potensial_ha,
                ],
                'tenaga_op' => collect($di->tenaga_op ?? [])->filter()->map(fn ($v, $k) => ['label' => self::TENAGA[$k] ?? ucfirst($k), 'n' => (int) $v])->values(),
                'url' => $internal ? route('master.daerah-irigasi.show', $di) : null,
            ],
            'profil' => $di->fotoProfil->take(DaerahIrigasi::MAKS_FOTO_PROFIL)->map(fn ($f) => [
                'kecil' => $f->url_kecil, 'besar' => $f->url, 'judul' => $f->judul,
                'tanggal' => $f->diambil_pada?->translatedFormat('d M Y'),
                'label' => trim(($f->judul ?? 'Foto profil D.I. '.$di->nama).($f->diambil_pada ? ' · '.$f->diambil_pada->translatedFormat('d M Y') : '')),
            ])->values(),
            'ringkasan' => $ring ? collect($ring)->only(['status', 'status_label', 'warna', 'jumlah_sub', 'sub_lapor', 'luas_tanam_ha', 'luas_kering_ha', 'luas_puso_ha', 'luas_panen_ha', 'laporan_terakhir', 'mt']) : null,
            'jadwal' => $this->jadwal($di, $tgl),
            'teknis' => $this->teknisDi($subs, $bangunan, $pintu, $ring),
            'sub' => $subs->map(fn ($s) => $this->sub($s, $terkini->get($s->id), $internal, $konteks))->sortBy(fn ($s) => [-StatusKondisi::URUTAN[$s['status']], $s['nama']])->values(),
            'air' => $this->air($di, $tgl, $internal),
            'riwayat' => $riwayat = $this->riwayat($ids, $dariRiwayat, $tgl, $di->id),
            'riwayat_judul' => $p && $p->rekap()
                ? 'Riwayat '.$p->label.(count($riwayat) < $p->jumlahHari() ? ' (per minggu)' : '')
                : 'Riwayat 30 hari',
            'bangunan' => $bangunan,
            'kejadian' => $this->kejadian($di, $subIds),
            'p3a' => P3a::where('daerah_irigasi_id', $di->id)->orderBy('nama')->pluck('nama'),
        ] + $this->fotoHarian($ids, $tgl, $internal, $di->id);
    }

    /**
     * Foto laporan dikelompokkan per tanggal, 30 hari per halaman (mundur dari $sampai).
     *
     * @return array{foto_harian: array, foto_sebelum: ?string}
     */
    public function fotoHarian(array $subIds, Carbon $sampai, bool $internal, ?int $diId = null): array
    {
        $dari = $sampai->copy()->subDays(self::HARI - 1);
        $laporan = $this->laporanBerfoto($subIds, $internal)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->with(['foto', 'subDaerahIrigasi:id,nama', 'user:id,name', 'petak.pintu:id,kode'])
            ->orderByDesc('tanggal')->orderBy('sub_daerah_irigasi_id')->get();
        // laporan air Manager (sungai, intake, saluran) yang berfoto ikut tampil per tanggal, di urutan pertama
        $air = $diId ? LaporanAir::where('daerah_irigasi_id', $diId)->whereHas('foto')
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])->with('foto', 'user:id,name', 'sesiPintu.pembacaan')->get() : collect();
        $laporan = $air->concat($laporan)->sortByDesc(fn ($l) => $l->tanggal->toDateString())->values();

        $grup = $laporan->groupBy(fn ($l) => $l->tanggal->toDateString())->map(function ($daftar, $tgl) use ($internal) {
            $t = Carbon::parse($tgl);

            return [
                'tanggal' => $tgl,
                'label' => $t->translatedFormat('l, d F Y'),
                'singkat' => $t->translatedFormat('d M'),
                'laporan' => $daftar->map(function ($l) use ($internal, $t) {
                    $st = $l->status();
                    if ($l instanceof LaporanAir) {
                        $label = 'Laporan Manager Air · bendung, intake & saluran primer';

                        return [
                            'id' => 'air-'.$l->id, 'sub' => $label,
                            'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
                            'kondisi' => $l->ringkasKondisi(),
                            'fase' => null, 'komoditas' => null, 'kendala' => $l->catatan, 'terverifikasi' => null,
                            'foto' => $l->foto->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url, 'label' => $label.' · '.$t->translatedFormat('d M Y')])->values(),
                        ] + ($internal ? [
                            'debit' => $l->debit_intake_lps, 'tma' => $l->tma_intake_cm, 'debit_sungai' => $l->debit_sungai_m3s,
                            'pelapor' => $l->user?->name, 'url' => route('laporan-air.show', $l),
                        ] : []);
                    }

                    return [
                        'id' => $l->id,
                        'sub' => $l->subDaerahIrigasi?->nama.(($k = $l->petak->pluck('pintu.kode')->filter()->implode(', ')) ? ' · Petak '.$k : ''),
                        'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
                        'kondisi' => LaporanHarian::KONDISI[$l->kondisi_saluran] ?? null,
                        'fase' => $l->ringkasFase(),
                        'komoditas' => $l->ringkasKomoditas(),
                        'kendala' => $l->kendala,
                        'terverifikasi' => $l->status_verifikasi === 'disetujui',
                        'foto' => $l->foto->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url, 'label' => trim(($l->subDaerahIrigasi?->nama ?? '').' · '.$t->translatedFormat('d M Y'), ' ·')])->values(),
                    ] + ($internal ? [
                        'debit' => $l->debit_intake_lps === null ? null : (float) $l->debit_intake_lps, 'tma' => $l->tma_cm === null ? null : (float) $l->tma_cm,
                        'pelapor' => $l->user?->name, 'url' => route('laporan.show', $l),
                    ] : []);
                })->values(),
            ];
        })->values();

        $lebihLama = max(
            $this->laporanBerfoto($subIds, $internal)->where('tanggal', '<', $dari->toDateString())->max('tanggal'),
            $diId ? LaporanAir::where('daerah_irigasi_id', $diId)->whereHas('foto')->where('tanggal', '<', $dari->toDateString())->max('tanggal') : null,
        );

        return ['foto_harian' => $grup, 'foto_sebelum' => $lebihLama ? Carbon::parse($lebihLama)->toDateString() : null];
    }

    private function laporanBerfoto(array $subIds, bool $internal)
    {
        return LaporanHarian::query()->whereIn('sub_daerah_irigasi_id', $subIds)->whereHas('foto')
            // publik tidak melihat laporan yang dikembalikan untuk perbaikan
            ->when(! $internal, fn ($q) => $q->where('status_verifikasi', '!=', 'perbaikan'));
    }

    /**
     * Status D.I. per hari (laporan berlaku ≤ 7 hari, sama dengan rekap & peta), dikelompokkan
     * per minggu bila rentang > 62 hari. "tanggal" = hari terakhir berfoto dalam kelompok (untuk lompat ke foto).
     */
    private function riwayat(array $subIds, Carbon $dari, Carbon $sampai, ?int $diId = null): array
    {
        $kode = array_flip(StatusKondisi::URUTAN);
        $laporan = LaporanHarian::query()->whereIn('sub_daerah_irigasi_id', $subIds)
            ->whereBetween('tanggal', [$dari->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString(), $sampai->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->withCount('foto')->orderBy('tanggal')->get();

        $hari = [];
        for ($d = $dari->copy(); $d->lte($sampai); $d->addDay()) {
            $hari[$d->toDateString()] = ['status' => 0, 'laporan' => 0, 'foto' => 0];
        }
        foreach ($laporan as $l) {
            $t = $l->tanggal->toDateString();
            if (isset($hari[$t])) {
                $hari[$t]['laporan']++;
                $hari[$t]['foto'] += $l->foto_count;
            }
        }
        foreach ($laporan->groupBy(fn ($l) => $l->sub_daerah_irigasi_id.':'.$l->user_id) as $baris) { // per Sub-D.I. / PPA
            $baris = $baris->values();
            $k = -1;
            foreach (array_keys($hari) as $t) {
                while ($k + 1 < $baris->count() && $baris[$k + 1]->tanggal->toDateString() <= $t) {
                    $k++;
                }
                if ($k >= 0 && $baris[$k]->tanggal->diffInDays(Carbon::parse($t)) < StatusKondisi::MASA_BERLAKU_HARI) {
                    $hari[$t]['status'] = max($hari[$t]['status'], StatusKondisi::URUTAN[$baris[$k]->status()]);
                }
            }
        }

        // kondisi saluran dari laporan air Manager
        if ($diId) {
            $air = LaporanAir::where('daerah_irigasi_id', $diId)
                ->whereBetween('tanggal', [$dari->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString(), $sampai->toDateString()])
                ->withCount('foto')->orderBy('tanggal')->get()->values();
            foreach ($air as $a) {
                $t = $a->tanggal->toDateString();
                if (isset($hari[$t])) {
                    $hari[$t]['laporan']++;
                    $hari[$t]['foto'] += $a->foto_count;
                }
            }
            $k = -1;
            foreach (array_keys($hari) as $t) {
                while ($k + 1 < $air->count() && $air[$k + 1]->tanggal->toDateString() <= $t) {
                    $k++;
                }
                if ($k >= 0 && $air[$k]->tanggal->diffInDays(Carbon::parse($t)) < StatusKondisi::MASA_BERLAKU_HARI) {
                    $hari[$t]['status'] = max($hari[$t]['status'], StatusKondisi::URUTAN[$air[$k]->status()]);
                }
            }
        }

        $mingguan = count($hari) > 62;
        $kelompok = [];
        foreach ($hari as $t => $h) {
            $c = Carbon::parse($t);
            if (! $mingguan || ! $kelompok || $c->isMonday()) {
                $kelompok[] = ['awal' => $c, 'akhir' => $c, 'status' => 0, 'laporan' => 0, 'foto' => 0, 'tanggal_foto' => null];
            }
            $g = &$kelompok[count($kelompok) - 1];
            $g['akhir'] = $c;
            $g['status'] = max($g['status'], $h['status']);
            $g['laporan'] += $h['laporan'];
            $g['foto'] += $h['foto'];
            if ($h['foto']) {
                $g['tanggal_foto'] = $t;
            }
            unset($g);
        }

        return array_map(function ($g) use ($kode, $mingguan) {
            $st = $kode[$g['status']];

            return [
                'tanggal' => $g['tanggal_foto'] ?? $g['akhir']->toDateString(),
                'label' => $mingguan ? $g['awal']->translatedFormat('d M').' – '.$g['akhir']->translatedFormat('d M') : $g['awal']->translatedFormat('d M'),
                'status' => $st, 'warna' => StatusKondisi::WARNA[$st], 'status_label' => StatusKondisi::label($st),
                'laporan' => $g['laporan'], 'foto' => $g['foto'],
            ];
        }, $kelompok);
    }

    /** Laporan air Manager terakhir yang masih berlaku. Publik: kondisi saluran saja (tanpa angka debit). */
    private function air(DaerahIrigasi $di, Carbon $tgl, bool $internal): ?array
    {
        $a = LaporanAir::where('daerah_irigasi_id', $di->id)
            ->whereBetween('tanggal', [$tgl->copy()->subDays(StatusKondisi::MASA_BERLAKU_HARI - 1)->toDateString(), $tgl->toDateString()])
            ->with('sesiPintu.pembacaan')->latest('tanggal')->latest('id')->first();
        if (! $a) {
            return null;
        }
        $st = $a->status();

        return [
            'tanggal' => $a->tanggal->translatedFormat('d M Y'),
            'kondisi' => $a->ringkasKondisi(),
            'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
        ] + ($internal ? [
            'debit_sungai' => $a->debit_sungai_m3s, 'tma_sungai' => $a->tma_sungai_cm,
            'debit_intake' => $a->debit_intake_lps, 'tma_intake' => $a->tma_intake_cm, 'bukaan' => $a->bukaan_intake_cm,
            'pemberian' => KodeIsian::PEMBERIAN_AIR[$a->status_pemberian] ?? null,
            'catatan' => $a->catatan, 'url' => route('laporan-air.show', $a),
        ] : []);
    }

    /** Foto laporan PPA terakhir per Sub-D.I. (pengganti bila Sub-D.I. belum punya foto profil). */
    private function fotoLaporanTerakhir(array $ids, Carbon $tgl, bool $internal)
    {
        if (! $ids) {
            return collect();
        }
        $saring = fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $ids)->whereHas('foto')->where('tanggal', '<=', $tgl->toDateString())
            ->when(! $internal, fn ($w) => $w->where('status_verifikasi', '!=', 'perbaikan'));
        $terakhir = $saring(LaporanHarian::query())->selectRaw('sub_daerah_irigasi_id, max(tanggal) as t')->groupBy('sub_daerah_irigasi_id')->get();
        if ($terakhir->isEmpty()) {
            return collect();
        }

        return $saring(LaporanHarian::query())->where(function ($q) use ($terakhir) {
            foreach ($terakhir as $r) {
                $q->orWhere(fn ($w) => $w->where('sub_daerah_irigasi_id', $r->sub_daerah_irigasi_id)->where('tanggal', Carbon::parse($r->t)->toDateString()));
            }
        })->with('foto')->orderByDesc('id')->get()->groupBy('sub_daerah_irigasi_id')
            ->map(fn ($daftar) => $daftar->flatMap(fn ($l) => $l->foto->map(fn ($f) => [
                'kecil' => $f->url_kecil, 'besar' => $f->url, 'judul' => 'Laporan PPA', 'tanggal' => $l->tanggal->translatedFormat('d M Y'),
                'label' => 'Laporan PPA · '.$l->tanggal->translatedFormat('d M Y'),
            ]))->take(6)->values());
    }

    /** Ringkasan data teknis jaringan D.I.: jumlah Sub-D.I., pintu per tingkat, petak tersier, bangunan per jenis. */
    private function teknisDi($subs, array $bangunan, $pintu, $ring): array
    {
        $tersier = $pintu->where('tingkat', 'tersier');

        return [
            'jumlah_sub' => $subs->count(),
            'luas_baku_sub' => round((float) $subs->sum('luas_ha'), 2),
            'luas_fungsional_sub' => round((float) $subs->sum('luas_fungsional_ha'), 2),
            'tertanam' => $ring ? (float) $ring['luas_tanam_ha'] : null,
            'sub_berbatas' => $subs->filter(fn ($s) => ! empty($s->geometri))->count(),
            'jumlah_petak' => $tersier->count(),
            'luas_petak' => round((float) $tersier->sum('luas_layanan_ha'), 2),
            'pintu' => collect(PintuAir::TINGKAT)->map(fn ($label, $k) => ['label' => $label, 'n' => $pintu->where('tingkat', $k)->count()])
                ->filter(fn ($r) => $r['n'] > 0)->values(),
            'bangunan' => collect($bangunan)->groupBy('jenis')->map(fn ($g, $j) => ['label' => $j, 'n' => $g->count()])->sortByDesc('n')->values(),
            'saluran_m' => round((float) $subs->sum('panjang_saluran_m'), 1),
        ];
    }

    private function sub($s, ?LaporanHarian $l, bool $internal, array $konteks = []): array
    {
        $st = StatusKondisi::dariLaporan($l);
        $bangunan = collect($konteks['bangunan'][$s->id] ?? []);
        $pintu = collect($konteks['pintu'][$s->id] ?? []);
        $tersier = $pintu->where('tingkat', 'tersier');
        $fungsional = (float) ($s->luas_fungsional_ha ?: $s->luas_ha);
        $tanam = $l ? (float) $l->luas_tanam_ha : null;
        $foto = $s->fotoProfil->take(SubDaerahIrigasi::MAKS_FOTO_PROFIL)->map(fn ($f) => [
            'kecil' => $f->url_kecil, 'besar' => $f->url, 'judul' => $f->judul,
            'tanggal' => $f->diambil_pada?->translatedFormat('d M Y'),
            'label' => trim(($f->judul ?? 'Sub-D.I. '.$s->nama).($f->diambil_pada ? ' · '.$f->diambil_pada->translatedFormat('d M Y') : '')),
        ])->values();
        // titik bangunan & pintu Sub-D.I.: untuk mengarahkan peta bila batas areal belum digambar
        $titik = $bangunan->filter(fn ($b) => $b['lat'] && $b['lng'])->map(fn ($b) => [round($b['lat'], 6), round($b['lng'], 6)])
            ->concat($pintu->filter(fn ($p) => $p->lat && $p->lng)->map(fn ($p) => [round($p->lat, 6), round($p->lng, 6)]))->values();
        $kondisiB = $bangunan->countBy(fn ($b) => $b['kondisi'] ?: 'belum');

        return [
            'id' => $s->id, 'nama' => $s->nama, 'luas_ha' => (float) $s->luas_ha, 'lokasi' => $s->lokasi,
            'luas_fungsional_ha' => $s->luas_fungsional_ha === null ? null : (float) $s->luas_fungsional_ha,
            'kecamatan' => $s->kecamatan, 'desa' => $s->desa, 'saluran' => $s->saluran,
            'panjang_saluran_m' => $s->panjang_saluran_m === null ? null : (float) $s->panjang_saluran_m,
            'keterangan' => $s->keterangan,
            'tertanam' => $tanam,
            'persen_tertanam' => $tanam !== null && $fungsional > 0 ? round(min(999, $tanam / $fungsional * 100), 1) : null,
            'foto' => $foto,
            'foto_laporan' => $foto->isEmpty() ? collect($konteks['foto'][$s->id] ?? [])->values() : [],
            'jumlah_petak' => $tersier->count(), 'luas_petak' => round((float) $tersier->sum('luas_layanan_ha'), 2),
            'jumlah_pintu' => $pintu->count(),
            'bangunan' => ['total' => $bangunan->count(), 'baik' => $kondisiB['baik'] ?? 0, 'rusak' => ($kondisiB['rusak_ringan'] ?? 0) + ($kondisiB['rusak_sedang'] ?? 0) + ($kondisiB['rusak_berat'] ?? 0)],
            'p3a' => $s->p3a->pluck('nama')->values(),
            'lat' => $s->lat, 'lng' => $s->lng, 'geometri' => $s->geometri ?: null, 'titik' => $titik,
            'url' => $internal && auth()->user()?->can('kelola-master') ? route('master.sub.edit', $s) : null,
            'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
            'laporan' => $l ? [
                'tanggal' => $l->tanggal->translatedFormat('d M Y'),
                'kondisi' => LaporanHarian::KONDISI[$l->kondisi_saluran] ?? null,
                'fase' => $l->ringkasFase(),
                'kekurangan_air' => (int) $l->getAttribute('kekurangan_air'),
                'komoditas' => $l->ringkasKomoditas(),
                'tanam' => (float) $l->luas_tanam_ha, 'kering' => (float) $l->luas_kering_ha, 'puso' => (float) $l->luas_puso_ha,
                'petak' => collect($l->getAttribute('petak_rinci') ?? [])->map(fn ($r) => [
                    'kode' => $r['kode'], 'nama' => $r['nama'], 'status' => $r['status'], 'status_label' => StatusKondisi::label($r['status']), 'warna' => StatusKondisi::WARNA[$r['status']],
                    'tanam' => $r['tanam'], 'kering' => $r['kering'], 'puso' => $r['puso'],
                    'jenis' => $r['jenis'], 'fase' => $r['fase'], 'umur' => $r['umur'], 'kecukupan' => $r['kecukupan'], 'kecukupan_label' => $r['kecukupan_label'],
                    'tanggal' => Carbon::parse($r['tanggal'])->translatedFormat('d M'),
                ] + ($internal ? ['debit' => $r['debit'], 'url' => route('laporan.show', $r['laporan_id'])] : []))->values(),
            ] + ($internal ? ['debit' => $l->debit_intake_lps === null ? null : (float) $l->debit_intake_lps, 'tma' => $l->tma_cm === null ? null : (float) $l->tma_cm, 'url' => route('laporan.show', $l)] : []) : null,
        ] + ($internal ? [
            'petugas' => $s->petugas->map(fn ($p) => ['nama' => $p->name, 'jabatan' => $p->jabatan, 'wa' => $p->waLink()])->values(),
        ] : []);
    }

    private function bangunan(DaerahIrigasi $di, bool $internal): array
    {
        $urut = array_flip(array_keys(Bangunan::JENIS));
        $boleh = $internal && auth()->user()?->can('kelola-master');

        return Bangunan::where('daerah_irigasi_id', $di->id)->with('foto', 'subDaerahIrigasi:id,nama')->get()
            ->sortBy(fn ($b) => [$urut[$b->jenis] ?? 99, $b->nama])
            ->map(fn (Bangunan $b) => [
                'id' => $b->id, 'nama' => $b->nama, 'jenis' => $b->jenis_label, 'lokasi' => $b->lokasi,
                'sub' => $b->subDaerahIrigasi?->nama, 'sub_id' => $b->sub_daerah_irigasi_id,
                'kondisi' => $b->kondisi, 'kondisi_label' => $b->kondisi_label, 'warna' => $b->warna_kondisi,
                'kondisi_per' => $b->kondisi_per?->translatedFormat('d M Y'),
                'keterangan' => $b->keterangan,
                // "Kode integrasi" sudah tampil sebagai kode aset; "Nama di SIGA" hanya relevan untuk admin
                'teknis' => collect($b->data_teknis ?? [])->except(['Kode integrasi', 'Nama di SIGA'])->map(fn ($v, $k) => ['label' => $k, 'nilai' => (string) $v])->values(),
                'lat' => $b->lat, 'lng' => $b->lng,
                'foto' => $b->foto->take(8)->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url, 'label' => $b->nama.' · '.$f->created_at->translatedFormat('d M Y')])->values(),
                'url' => $boleh ? route('master.bangunan.edit', $b) : null,
            ])->values()->all();
    }

    private function kejadian(DaerahIrigasi $di, ?array $subIds): array
    {
        return Kejadian::where('daerah_irigasi_id', $di->id)
            ->when($subIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('sub_daerah_irigasi_id')->orWhereIn('sub_daerah_irigasi_id', $subIds)))
            ->with('foto')->orderByDesc('tanggal_lapor')->get()
            ->map(fn (Kejadian $k) => [
                'id' => $k->id, 'jenis' => $k->jenis_kegagalan, 'desa' => $k->desa, 'kecamatan' => $k->kecamatan,
                'status' => $k->status_penanganan, 'warna' => Kejadian::STATUS[$k->status_penanganan] ?? '#9AA4A8',
                'luas_terdampak_ha' => (float) $k->luas_terdampak_ha, 'tanggal' => $k->tanggal_lapor?->translatedFormat('d M Y'),
                'lat' => $k->lat, 'lng' => $k->lng,
                'foto' => $k->foto->first() ? ['kecil' => $k->foto->first()->url_kecil] : null,
            ])->all();
    }

    private function jadwal(DaerahIrigasi $di, Carbon $tgl): array
    {
        return $di->jadwalTanam->filter(fn ($j) => $j->selesai->gte($tgl->copy()->subMonths(4)) && $j->mulai->lte($tgl->copy()->addMonths(8)))
            ->sortBy('mulai')->take(4)
            ->map(fn ($j) => [
                'label' => $j->label, 'komoditas' => $j->komoditas,
                'mulai' => $j->mulai->translatedFormat('d M'), 'selesai' => $j->selesai->translatedFormat('d M Y'),
                'berjalan' => $tgl->between($j->mulai, $j->selesai),
                'lewat' => $j->selesai->lt($tgl),
            ])->values()->all();
    }
}
