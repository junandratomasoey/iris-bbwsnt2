<?php

namespace App\Http\Controllers;

use App\Models\Bangunan;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\SubDaerahIrigasi;
use App\Models\WilayahSatgas;
use App\Services\KondisiService;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PetaController extends Controller
{
    public function __construct(private KondisiService $kondisi) {}

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
        $tgl = $this->tanggal($request);
        $di = $this->kondisi->ringkasanDi($tgl)->values()
            ->map(fn ($d) => collect($d)->except(['sub_per_status', 'debit_total_lps'])->all());

        $kejadian = Kejadian::with('kabupaten:id,nama')->whereNotNull('lat')->get()->map(fn ($k) => [
            'lat' => $k->lat, 'lng' => $k->lng, 'jenis' => $k->jenis_kegagalan, 'penyebab' => $k->penyebab,
            'desa' => $k->desa, 'kecamatan' => $k->kecamatan, 'kabupaten' => $k->kabupaten?->nama,
            'luas_terdampak_ha' => (float) $k->luas_terdampak_ha, 'status' => $k->status_penanganan,
            'warna' => Kejadian::STATUS[$k->status_penanganan] ?? '#9AA4A8',
        ]);

        return response()->json([
            'tanggal' => $tgl->toDateString(),
            'daerah_irigasi' => $di,
            'kejadian' => $kejadian,
            'legenda' => $this->legenda(),
        ])->header('Cache-Control', 'public, max-age=300');
    }

    public function dataInternal(Request $request)
    {
        $user = $request->user();
        $tgl = $this->tanggal($request);
        $subIds = $user->subIdsTerjangkau();
        $ringkasan = $this->kondisi->ringkasanDi($tgl, $subIds);
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
                        'foto' => $l->foto->take(3)->map->url->values(), 'url' => route('laporan.show', $l),
                    ] : null,
                    'petugas' => $s->petugas->map(fn ($p) => ['nama' => $p->name, 'jabatan' => $p->jabatan, 'wa' => $p->waLink()])->values(),
                ];
            })->filter()->values();

        $kejadian = Kejadian::with('kabupaten:id,nama')->whereNotNull('lat')
            ->when($subIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('sub_daerah_irigasi_id')->orWhereIn('sub_daerah_irigasi_id', $subIds)))
            ->get()->map(fn ($k) => [
                'lat' => $k->lat, 'lng' => $k->lng, 'jenis' => $k->jenis_kegagalan, 'penyebab' => $k->penyebab,
                'desa' => $k->desa, 'kecamatan' => $k->kecamatan, 'kabupaten' => $k->kabupaten?->nama,
                'luas_terdampak_ha' => (float) $k->luas_terdampak_ha, 'status' => $k->status_penanganan,
                'warna' => Kejadian::STATUS[$k->status_penanganan] ?? '#9AA4A8',
                'pelapor' => $k->pelapor_nama, 'wa' => wa_link($k->pelapor_hp), 'url' => route('kejadian.show', $k),
            ]);

        $bangunan = Bangunan::whereNotNull('lat')
            ->when($subIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $ringkasan->keys()))
            ->get(['id', 'nama', 'jenis', 'lat', 'lng', 'kondisi']);

        return response()->json([
            'tanggal' => $tgl->toDateString(),
            'daerah_irigasi' => $ringkasan->values(),
            'sub' => $subs,
            'kejadian' => $kejadian,
            'bangunan' => $bangunan,
            'legenda' => $this->legenda(),
        ]);
    }

    private function tanggal(Request $request): Carbon
    {
        $t = $request->date('tanggal') ?? today();

        return $t->isAfter(today()) ? today() : $t->startOfDay();
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
