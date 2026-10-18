<?php

namespace App\Http\Controllers;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\SubDaerahIrigasi;
use App\Models\WilayahSatgas;
use App\Services\DetailDiService;
use App\Services\RekapService;
use App\Support\Periode;
use App\Services\KondisiService;
use App\Support\StatusKondisi;
use Illuminate\Http\Request;

class PetaController extends Controller
{
    public function __construct(private KondisiService $kondisi, private DetailDiService $detail, private RekapService $rekap) {}

    public function publik()
    {
        return view('peta.publik', $this->opsiFilter());
    }

    public function internal()
    {
        return view('peta.internal', $this->opsiFilter());
    }

    /** Data peta untuk publik & Dinas Pertanian: tanpa nomor HP, foto internal, dan nama pelapor. */
    public function dataPublik(Request $request)
    {
        $periode = Periode::dariRequest($request);
        $tgl = $periode->sampai;
        $di = $this->daftarDi($request, $periode, null)
            ->map(fn ($d) => collect($d)->except(['sub_per_status', 'debit_total_lps', 'debit_rata_lps', 'debit_min_lps'])->all());

        $kejadian = Kejadian::with('kabupaten:id,nama', 'daerahIrigasi:id,nama', 'foto')->whereNotNull('lat')->get()->map(fn ($k) => [
            'id' => $k->id, 'di' => $k->daerahIrigasi?->nama,
            'lat' => $k->lat, 'lng' => $k->lng, 'jenis' => $k->jenis_kegagalan, 'penyebab' => $k->penyebab,
            'desa' => $k->desa, 'kecamatan' => $k->kecamatan, 'kabupaten' => $k->kabupaten?->nama,
            'luas_terdampak_ha' => (float) $k->luas_terdampak_ha, 'luas_tertangani_ha' => (float) $k->luas_tertangani_ha,
            'status' => $k->status_penanganan, 'warna' => Kejadian::STATUS[$k->status_penanganan] ?? '#9AA4A8',
            'foto' => $this->fotoKejadian($k),
        ]);

        return response()->json([
            'tanggal' => $tgl->toDateString(),
            'periode' => $this->infoPeriode($request, $periode),
            'daerah_irigasi' => $di,
            'kejadian' => $kejadian,
            'legenda' => $this->legenda(),
        ])->header('Cache-Control', 'no-cache, private');
    }

    public function dataInternal(Request $request)
    {
        $user = $request->user();
        $periode = Periode::dariRequest($request);
        $tgl = $periode->sampai;
        $subIds = $user->subIdsTerjangkau();
        $ringkasan = $this->kondisi->ringkasanDi($tgl, $subIds);
        $daftarDi = $this->daftarDi($request, $periode, $subIds, $ringkasan);
        $laporan = $this->kondisi->laporanTerkini($tgl, $subIds)->load('foto', 'user:id,name');

        $subs = SubDaerahIrigasi::with(['daerahIrigasi:id,nama,lat,lng', 'petugas:id,name,no_hp,jabatan'])
            ->when($subIds !== null, fn ($q) => $q->whereIn('id', $subIds))->get()
            ->map(function ($s) use ($laporan) {
                $titik = $s->titik();
                if (! $titik) {
                    return null;
                }
                $l = $laporan->get($s->id);
                $st = StatusKondisi::dariLaporan($l);

                return [
                    'id' => $s->id, 'nama' => $s->nama, 'di' => $s->daerahIrigasi->nama, 'di_id' => $s->daerah_irigasi_id,
                    'luas_ha' => $s->luas_ha, 'lat' => $titik[0], 'lng' => $titik[1], 'titik_pasti' => $titik[2],
                    'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
                    'laporan' => $l ? [
                        'id' => $l->id, 'tanggal' => $l->tanggal->translatedFormat('d M Y'), 'debit' => (float) $l->debit_intake_lps,
                        'tma' => (float) $l->tma_cm, 'kondisi' => $l->kondisi_saluran, 'tanam' => (float) $l->luas_tanam_ha,
                        'kering' => (float) $l->luas_kering_ha, 'puso' => (float) $l->luas_puso_ha, 'pelapor' => $l->user?->name,
                        'foto' => $l->foto->take(3)->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url])->values(), 'url' => route('laporan.show', $l),
                    ] : null,
                    'petugas' => $s->petugas->map(fn ($p) => ['nama' => $p->name, 'jabatan' => $p->jabatan, 'wa' => $p->waLink()])->values(),
                ];
            })->filter()->values();

        $kejadian = Kejadian::with('kabupaten:id,nama', 'daerahIrigasi:id,nama', 'foto')->whereNotNull('lat')
            ->when($subIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('sub_daerah_irigasi_id')->orWhereIn('sub_daerah_irigasi_id', $subIds)))
            ->get()->map(fn ($k) => [
                'id' => $k->id, 'di' => $k->daerahIrigasi?->nama,
                'lat' => $k->lat, 'lng' => $k->lng, 'jenis' => $k->jenis_kegagalan, 'penyebab' => $k->penyebab,
                'desa' => $k->desa, 'kecamatan' => $k->kecamatan, 'kabupaten' => $k->kabupaten?->nama,
                'luas_terdampak_ha' => (float) $k->luas_terdampak_ha, 'status' => $k->status_penanganan,
                'warna' => Kejadian::STATUS[$k->status_penanganan] ?? '#9AA4A8',
                'pelapor' => $k->pelapor_nama, 'wa' => wa_link($k->pelapor_hp), 'url' => route('kejadian.show', $k),
                'luas_tertangani_ha' => (float) $k->luas_tertangani_ha, 'foto' => $this->fotoKejadian($k),
            ]);

        $bangunan = Bangunan::whereNotNull('lat')
            ->when($subIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $ringkasan->keys()))
            ->get(['id', 'nama', 'jenis', 'lat', 'lng', 'kondisi']);

        return response()->json([
            'tanggal' => $tgl->toDateString(),
            'periode' => $this->infoPeriode($request, $periode),
            'daerah_irigasi' => $daftarDi,
            'sub' => $subs,
            'kejadian' => $kejadian,
            'bangunan' => $bangunan,
            'legenda' => $this->legenda(),
        ]);
    }

    /** Panel detail D.I. (publik). ?sebelum=YYYY-MM-DD hanya mengembalikan foto 30 hari sebelum tanggal itu. */
    public function detailPublik(Request $request, DaerahIrigasi $di)
    {
        return $this->detail($request, $di, false, null);
    }

    public function detailInternal(Request $request, DaerahIrigasi $di)
    {
        $subIds = $request->user()->subIdsTerjangkau();
        if ($subIds !== null) {
            $subIds = $di->subDaerahIrigasi()->whereIn('id', $subIds)->pluck('id')->all();
            abort_if(! $subIds, 403, 'D.I. ini di luar wilayah tugas Anda.');
        }

        return $this->detail($request, $di, true, $subIds);
    }

    private function detail(Request $request, DaerahIrigasi $di, bool $internal, ?array $subIds)
    {
        if ($request->filled('sebelum')) {
            $sampai = $request->date('sebelum');
            $ids = $subIds ?? $di->subDaerahIrigasi()->pluck('id')->all();
            $data = $this->detail->fotoHarian($ids, $sampai->startOfDay(), $internal);
        } else {
            $data = $this->detail->data($di, Periode::dariRequest($request), $internal, $subIds);
        }

        return response()->json($data)->header('Cache-Control', 'no-cache, private');
    }

    /** Maks. 6 foto per kejadian: urut sebelum → sesudah → lainnya. */
    private function fotoKejadian(Kejadian $k): array
    {
        $urut = ['sebelum' => 0, 'sesudah' => 1];

        return $k->foto->sortBy(fn ($f) => [$urut[$f->keterangan] ?? 2, $f->id])->take(6)
            ->map(fn ($f) => ['kecil' => $f->url_kecil, 'besar' => $f->url, 'label' => $f->label])->values()->all();
    }

    /**
     * Daftar D.I. untuk peta. "Hari ini": kondisi terkini. Periode lain: rekap, dengan warna
     * status terberat (default) atau tersering (?warna=tersering).
     */
    private function daftarDi(Request $request, Periode $p, ?array $subIds, $ringkasan = null)
    {
        if (! $p->rekap()) {
            return ($ringkasan ?? $this->kondisi->ringkasanDi($p->sampai, $subIds))->values();
        }
        $mode = $request->input('warna') === 'tersering' ? 'tersering' : 'terberat';

        return collect($this->rekap->rekap($p, $subIds)['di'])->values()->map(function ($d) use ($mode) {
            $st = RekapService::statusMode($d, $mode);

            return [
                'id' => $d['id'], 'kode' => $d['kode'], 'nama' => $d['nama'], 'kabupaten' => $d['kabupaten'], 'satgas' => $d['satgas'],
                'luas_ha' => $d['luas_ha'], 'lat' => $d['lat'], 'lng' => $d['lng'],
                'status' => $st, 'status_label' => StatusKondisi::label($st), 'warna' => StatusKondisi::WARNA[$st],
                'terberat' => $d['terberat'], 'tersering' => $d['tersering'],
                'hari' => $d['hari'], 'hari_berisiko' => $d['hari_berisiko'],
                'jumlah_sub' => $d['jumlah_sub'], 'laporan' => $d['laporan'], 'kepatuhan' => $d['kepatuhan'],
                'luas_tanam_ha' => $d['puncak_tanam_ha'], 'luas_kering_ha' => $d['puncak_kering_ha'],
                'luas_puso_ha' => $d['puncak_puso_ha'], 'luas_panen_ha' => $d['puncak_panen_ha'],
                'debit_rata_lps' => $d['debit_rata_lps'], 'debit_min_lps' => $d['debit_min_lps'],
                'keterangan' => "{$d['hari']['kritis']} hari kritis · {$d['hari']['siaga']} siaga · lapor {$d['kepatuhan']}%",
            ];
        });
    }

    private function infoPeriode(Request $request, Periode $p): array
    {
        return [
            'kode' => $p->kode, 'label' => $p->label, 'keterangan' => $p->keterangan(), 'rekap' => $p->rekap(),
            'dari' => $p->dari->toDateString(), 'sampai' => $p->sampai->toDateString(), 'jumlah_hari' => $p->jumlahHari(),
            'warna' => $request->input('warna') === 'tersering' ? 'tersering' : 'terberat',
        ];
    }

    private function legenda(): array
    {
        return collect(StatusKondisi::LABEL)->map(fn ($l, $k) => ['label' => $l, 'warna' => StatusKondisi::WARNA[$k]])->all();
    }

    private function opsiFilter(): array
    {
        return [
            'kabupatenList' => Kabupaten::whereHas('daerahIrigasi')->orderBy('nama')->pluck('nama'),
            'satgasList' => WilayahSatgas::orderBy('id')->get(['kode', 'cakupan']),
        ];
    }
}
