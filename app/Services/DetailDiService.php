<?php

namespace App\Services;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\Kejadian;
use App\Models\LaporanHarian;
use App\Models\P3a;
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

    public function data(DaerahIrigasi $di, Carbon $tgl, bool $internal, ?array $subIds = null): array
    {
        $di->load(['kabupaten.wilayahSatgas', 'jadwalTanam', 'subDaerahIrigasi' => fn ($q) => $q->when($subIds !== null, fn ($w) => $w->whereIn('id', $subIds))]);
        $subs = $di->subDaerahIrigasi;
        if ($internal) {
            $subs->load('petugas:id,name,no_hp,jabatan');
        }
        $ids = $subs->pluck('id')->all();
        $terkini = $this->kondisi->laporanTerkini($tgl, $ids);
        $ring = $this->kondisi->ringkasanDi($tgl, $subIds)->get($di->id);

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
            'ringkasan' => $ring ? collect($ring)->only(['status', 'status_label', 'warna', 'jumlah_sub', 'sub_lapor', 'luas_tanam_ha', 'luas_kering_ha', 'luas_puso_ha', 'luas_panen_ha', 'laporan_terakhir', 'mt']) : null,
            'jadwal' => $this->jadwal($di, $tgl),
            'sub' => $subs->map(fn ($s) => $this->sub($s, $terkini->get($s->id), $internal))->sortBy(fn ($s) => [-StatusKondisi::URUTAN[$s['status']], $s['nama']])->values(),
            'riwayat' => $this->riwayat($ids, $tgl, $internal),
            'bangunan' => $this->bangunan($di, $internal),
            'kejadian' => $this->kejadian($di, $subIds),
            'p3a' => P3a::where('daerah_irigasi_id', $di->id)->orderBy('nama')->pluck('nama'),
        ] + $this->fotoHarian($ids, $tgl, $internal);
    }

    /**
     * Foto laporan dikelompokkan per tanggal, 30 hari per halaman (mundur dari $sampai).
     *
     * @return array{foto_harian: array, foto_sebelum: ?string}
     */
    public function fotoHarian(array $subIds, Carbon $sampai, bool $internal): array
    {
        $dari = $sampai->copy()->subDays(self::HARI - 1);
        $laporan = $this->laporanBerfoto($subIds, $internal)
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->with(['foto', 'subDaerahIrigasi:id,nama', 'user:id,name'])
            ->orderByDesc('tanggal')->orderBy('sub_daerah_irigasi_id')->get();

        $grup = $laporan->groupBy(fn ($l) => $l->tanggal->toDateString())->map(function ($daftar, $tgl) use ($internal) {
            $t = Carbon::parse($tgl);

            return [
                'tanggal' => $tgl,
                'label' => $t->translatedFormat('l, d F Y'),
                'singkat' => $t->translatedFormat('d M'),
                'laporan' => $daftar->map(function (LaporanHarian $l) use ($internal, $t) {
                    $st = $l->status();

                    return [
                        'id' => $l->id,
                        'sub' => $l->subDaerahIrigasi?->nama,
                        'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
                        'kondisi' => LaporanHarian::KONDISI[$l->kondisi_saluran] ?? $l->kondisi_saluran,
                        'fase' => LaporanHarian::FASE[$l->fase_tanaman] ?? null,
                        'kendala' => $l->kendala,
                        'terverifikasi' => $l->status_verifikasi === 'disetujui',
                        'foto' => $l->foto->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url, 'label' => trim(($l->subDaerahIrigasi?->nama ?? '').' · '.$t->translatedFormat('d M Y'), ' ·')])->values(),
                    ] + ($internal ? [
                        'debit' => (float) $l->debit_intake_lps, 'tma' => (float) $l->tma_cm,
                        'pelapor' => $l->user?->name, 'url' => route('laporan.show', $l),
                    ] : []);
                })->values(),
            ];
        })->values();

        $lebihLama = $this->laporanBerfoto($subIds, $internal)->where('tanggal', '<', $dari->toDateString())->max('tanggal');

        return ['foto_harian' => $grup, 'foto_sebelum' => $lebihLama ? Carbon::parse($lebihLama)->toDateString() : null];
    }

    private function laporanBerfoto(array $subIds, bool $internal)
    {
        return LaporanHarian::query()->whereIn('sub_daerah_irigasi_id', $subIds)->whereHas('foto')
            // publik tidak melihat laporan yang dikembalikan untuk perbaikan
            ->when(! $internal, fn ($q) => $q->where('status_verifikasi', '!=', 'perbaikan'));
    }

    /** Status terberat per hari selama 30 hari, untuk strip riwayat. */
    private function riwayat(array $subIds, Carbon $tgl, bool $internal): array
    {
        $dari = $tgl->copy()->subDays(self::HARI - 1);
        $laporan = LaporanHarian::query()->whereIn('sub_daerah_irigasi_id', $subIds)
            ->whereBetween('tanggal', [$dari->toDateString(), $tgl->toDateString()])
            ->where('status_verifikasi', '!=', 'perbaikan')
            ->withCount('foto')->get()->groupBy(fn ($l) => $l->tanggal->toDateString());

        $hasil = [];
        for ($d = $dari->copy(); $d->lte($tgl); $d->addDay()) {
            $hari = $laporan->get($d->toDateString(), collect());
            $st = $hari->reduce(fn ($m, $l) => StatusKondisi::maks($m, $l->status()), StatusKondisi::KOSONG);
            $hasil[] = [
                'tanggal' => $d->toDateString(), 'label' => $d->translatedFormat('d M'),
                'status' => $st, 'warna' => StatusKondisi::WARNA[$st], 'status_label' => StatusKondisi::label($st),
                'laporan' => $hari->count(), 'foto' => (int) $hari->sum('foto_count'),
            ];
        }

        return $hasil;
    }

    private function sub($s, ?LaporanHarian $l, bool $internal): array
    {
        $st = StatusKondisi::dariLaporan($l);

        return [
            'id' => $s->id, 'nama' => $s->nama, 'luas_ha' => (float) $s->luas_ha, 'lokasi' => $s->lokasi,
            'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
            'laporan' => $l ? [
                'tanggal' => $l->tanggal->translatedFormat('d M Y'),
                'kondisi' => LaporanHarian::KONDISI[$l->kondisi_saluran] ?? $l->kondisi_saluran,
                'fase' => LaporanHarian::FASE[$l->fase_tanaman] ?? null,
                'tanam' => (float) $l->luas_tanam_ha, 'kering' => (float) $l->luas_kering_ha, 'puso' => (float) $l->luas_puso_ha,
            ] + ($internal ? ['debit' => (float) $l->debit_intake_lps, 'tma' => (float) $l->tma_cm, 'url' => route('laporan.show', $l)] : []) : null,
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
                'sub' => $b->subDaerahIrigasi?->nama,
                'kondisi' => $b->kondisi, 'kondisi_label' => $b->kondisi_label, 'warna' => $b->warna_kondisi,
                'kondisi_per' => $b->kondisi_per?->translatedFormat('d M Y'),
                'keterangan' => $b->keterangan,
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
