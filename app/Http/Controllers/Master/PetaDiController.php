<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\PetaDi;
use App\Services\PetaDiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** CRUD batas D.I. (poligon) per lapisan peta. */
class PetaDiController extends Controller
{
    private const KOLOM_NAMA = ['Nm_Inf', 'nama', 'Nama', 'NAMA', 'nama_di', 'Nama_DI', 'NAMA_DI', 'name', 'Name', 'NAME', 'DI'];

    public function __construct(private PetaDiService $peta) {}

    public function index(Request $request)
    {
        $this->authorize('lihat-master');
        $lapisan = $request->input('lapisan', 'fungsional');
        abort_unless(isset(PetaDi::LAPISAN[$lapisan]), 404);

        $daftar = PetaDi::with('daerahIrigasi:id,kode,nama')
            ->select(['id', 'lapisan', 'daerah_irigasi_id', 'nama', 'luas_ha', 'sumber', 'updated_at'])
            ->where('lapisan', $lapisan)
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->orderByRaw('daerah_irigasi_id IS NULL DESC')->orderBy('nama')->get();

        $jumlah = PetaDi::selectRaw('lapisan, count(*) n')->groupBy('lapisan')->pluck('n', 'lapisan');
        $tanpaPeta = DaerahIrigasi::whereDoesntHave('peta', fn ($q) => $q->where('lapisan', $lapisan))->orderBy('nama')->get(['id', 'nama']);

        return view('master.peta.index', compact('daftar', 'lapisan', 'jumlah', 'tanpaPeta'));
    }

    public function create(Request $request)
    {
        $this->authorize('kelola-master');
        $peta = new PetaDi([
            'lapisan' => $request->input('lapisan', 'fungsional'),
            'daerah_irigasi_id' => $request->integer('di') ?: null,
        ]);
        $peta->nama = $peta->daerah_irigasi_id ? 'D.I. '.DaerahIrigasi::find($peta->daerah_irigasi_id)?->nama : '';

        return view('master.peta.form', $this->dataForm($peta));
    }

    public function store(Request $request)
    {
        $this->authorize('kelola-master');
        [$data, $geometri] = $this->validasi($request);
        $peta = $this->peta->simpan(new PetaDi($data + ['sumber' => 'Digitasi di IRIS']), $geometri);

        return redirect()->route('master.peta.index', ['lapisan' => $peta->lapisan])->with('ok', "Batas {$peta->nama} disimpan (".angka($peta->luas_ha, 2).' Ha).');
    }

    public function edit(PetaDi $peta)
    {
        $this->authorize('kelola-master');

        return view('master.peta.form', $this->dataForm($peta));
    }

    public function update(Request $request, PetaDi $peta)
    {
        $this->authorize('kelola-master');
        [$data, $geometri] = $this->validasi($request, $peta);
        $lapisanLama = $peta->lapisan;
        $peta->fill($data);
        $this->peta->simpan($peta, $geometri);
        if ($lapisanLama !== $peta->lapisan) {
            $this->peta->bangunFile($lapisanLama);
        }

        return redirect()->route('master.peta.index', ['lapisan' => $peta->lapisan])->with('ok', "Batas {$peta->nama} diperbarui (".angka($peta->luas_ha, 2).' Ha).');
    }

    public function destroy(PetaDi $peta)
    {
        $this->authorize('kelola-master');
        [$di, $lapisan, $nama] = [$peta->daerah_irigasi_id, $peta->lapisan, $peta->nama];
        $peta->delete();
        $this->peta->sinkronDi($di, $lapisan);
        $this->peta->bangunFile($lapisan);

        return redirect()->route('master.peta.index', ['lapisan' => $lapisan])->with('ok', "Batas $nama dihapus.");
    }

    // ---------------------------------------------------------------- import / ekspor

    public function importForm(Request $request)
    {
        $this->authorize('kelola-master');

        return view('master.peta.import', ['lapisan' => $request->input('lapisan', 'fungsional'), 'maksUpload' => $this->maksUploadMb()]);
    }

    public function import(Request $request)
    {
        $this->authorize('kelola-master');
        $data = $request->validate([
            'lapisan' => ['required', Rule::in(array_keys(PetaDi::LAPISAN))],
            'mode' => ['required', Rule::in(['ganti_di', 'tambah', 'ganti_semua'])],
            'kolom_nama' => ['nullable', 'string', 'max:50'],
            'berkas' => ['required', 'file', 'max:51200'],
            'konfirmasi' => ['required_if:mode,ganti_semua', 'accepted_if:mode,ganti_semua'],
        ], [
            'konfirmasi.required_if' => 'Centang konfirmasi untuk mengganti seluruh lapisan.',
            'konfirmasi.accepted_if' => 'Centang konfirmasi untuk mengganti seluruh lapisan.',
        ]);
        // File batas resolusi penuh bisa butuh >128 MB saat diproses; naikkan bila hosting mengizinkan.
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
        $ext = strtolower($request->file('berkas')->getClientOriginalExtension());
        abort_unless(in_array($ext, ['geojson', 'json', 'zip']), 422, 'Format file harus .geojson, .json, atau .zip.');

        $fitur = $this->peta->bacaFile($request->file('berkas')->getRealPath(), $request->file('berkas')->getClientOriginalName());

        // Kelompokkan potongan poligon dengan nama yang sama menjadi satu MultiPolygon per D.I.
        $grup = [];
        foreach ($fitur as $f) {
            $nama = $this->namaFitur($f['properties'], $data['kolom_nama'] ?? null);
            $di = $this->peta->cariDi($nama);
            $kunci = $di ? 'di:'.$di->id : 'nama:'.PetaDiService::kunci($nama ?: 'tanpa-nama');
            $grup[$kunci] ??= ['nama' => $di ? 'D.I. '.$di->nama : ($nama ?: 'Tanpa nama'), 'di' => $di?->id, 'poligon' => []];
            array_push($grup[$kunci]['poligon'], ...$this->peta->poligon($f['geometry']));
        }

        $sumber = 'Import '.$request->file('berkas')->getClientOriginalName().' ('.now()->format('d/m/Y').')';
        $ringkas = DB::transaction(function () use ($grup, $data, $sumber) {
            $lapisan = $data['lapisan'];
            if ($data['mode'] === 'ganti_semua') {
                PetaDi::where('lapisan', $lapisan)->delete();
            }
            $hasil = ['baru' => 0, 'diganti' => 0, 'tanpa_di' => []];
            $diTersentuh = [];
            foreach ($grup as $g) {
                if ($data['mode'] === 'ganti_di' && $g['di']) {
                    $hasil['diganti'] += PetaDi::where('lapisan', $lapisan)->where('daerah_irigasi_id', $g['di'])->delete() ? 1 : 0;
                }
                $geom = count($g['poligon']) === 1
                    ? ['type' => 'Polygon', 'coordinates' => $g['poligon'][0]]
                    : ['type' => 'MultiPolygon', 'coordinates' => $g['poligon']];
                $p = new PetaDi(['lapisan' => $lapisan, 'daerah_irigasi_id' => $g['di'], 'nama' => $g['nama'], 'sumber' => $sumber]);
                [$lat, $lng] = $this->peta->titikTengah($geom);
                $p->fill(['geometri' => $geom, 'luas_ha' => $this->peta->luasHa($geom), 'lat' => $lat, 'lng' => $lng])->save();
                $hasil['baru']++;
                if ($g['di']) {
                    $diTersentuh[$g['di']] = true;
                } else {
                    $hasil['tanpa_di'][] = $g['nama'];
                }
            }
            $semuaDi = $data['mode'] === 'ganti_semua' ? DaerahIrigasi::pluck('id')->all() : array_keys($diTersentuh);
            foreach ($semuaDi as $id) {
                $this->peta->sinkronDi($id, $lapisan);
            }

            return $hasil;
        });
        $this->peta->bangunFile($data['lapisan']);

        $pesan = "{$ringkas['baru']} batas diimpor ke lapisan ".PetaDi::LAPISAN[$data['lapisan']].'.';
        if ($ringkas['tanpa_di']) {
            $pesan .= ' '.count($ringkas['tanpa_di']).' belum terhubung ke D.I. ('.implode(', ', array_slice($ringkas['tanpa_di'], 0, 5)).') — buka dan pilih D.I.-nya.';
        }

        return redirect()->route('master.peta.index', ['lapisan' => $data['lapisan']])->with('ok', $pesan);
    }

    /** Unduh geometri penuh (tanpa penyederhanaan) satu lapisan atau satu D.I. */
    public function unduh(Request $request)
    {
        $lapisan = $request->input('lapisan', 'fungsional');
        abort_unless(isset(PetaDi::LAPISAN[$lapisan]), 404);
        $fitur = PetaDi::with('daerahIrigasi:id,kode,nama')->where('lapisan', $lapisan)
            ->when($request->filled('id'), fn ($q) => $q->whereKey($request->integer('id')))
            ->get()->map(fn ($p) => [
                'type' => 'Feature',
                'properties' => ['nama' => $p->nama, 'kode_di' => $p->daerahIrigasi?->kode, 'lapisan' => $p->lapisan, 'luas_ha' => $p->luas_ha, 'sumber' => $p->sumber, 'diperbarui' => $p->updated_at?->toDateTimeString()],
                'geometry' => $p->geometri,
            ]);
        $nama = 'iris-'.$lapisan.($request->filled('id') ? '-'.$request->integer('id') : '').'-'.now()->format('Ymd').'.geojson';

        return response()->streamDownload(function () use ($fitur, $lapisan) {
            echo json_encode(['type' => 'FeatureCollection', 'name' => $lapisan, 'crs' => ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:OGC:1.3:CRS84']], 'features' => $fitur], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $nama, ['Content-Type' => 'application/geo+json']);
    }

    /** Bangun ulang ketiga file tampilan (mis. setelah restore database). */
    public function bangunUlang()
    {
        $this->authorize('kelola-master');
        @ini_set('memory_limit', '512M');
        @set_time_limit(180);
        foreach (array_keys(PetaDi::LAPISAN) as $l) {
            $this->peta->bangunFile($l);
        }

        return back()->with('ok', 'File peta dibangun ulang dari database.');
    }

    // ----------------------------------------------------------------

    private function validasi(Request $request, ?PetaDi $peta = null): array
    {
        $data = $request->validate([
            'lapisan' => ['required', Rule::in(array_keys(PetaDi::LAPISAN))],
            'daerah_irigasi_id' => ['nullable', 'exists:daerah_irigasi,id'],
            'nama' => ['required', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'geometri' => ['required', 'json'],
        ], ['geometri.required' => 'Gambar minimal satu poligon di peta.']);
        $geom = json_decode($data['geometri'], true);
        $this->peta->validasiGeometri($geom);
        unset($data['geometri']);

        return [$data, $geom];
    }

    private function dataForm(PetaDi $peta): array
    {
        $di = $peta->daerahIrigasi ?? ($peta->daerah_irigasi_id ? DaerahIrigasi::find($peta->daerah_irigasi_id) : null);

        return [
            'peta' => $peta,
            'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama', 'lat', 'lng']),
            'pusat' => $peta->lat ? [$peta->lat, $peta->lng] : ($di?->lat ? [$di->lat, $di->lng] : null),
        ];
    }

    private function namaFitur(array $props, ?string $kolom): ?string
    {
        if ($kolom && isset($props[$kolom])) {
            return trim((string) $props[$kolom]);
        }
        foreach (self::KOLOM_NAMA as $k) {
            if (! empty($props[$k])) {
                return trim((string) $props[$k]);
            }
        }

        return null;
    }

    private function maksUploadMb(): int
    {
        $ke = fn ($v) => (int) $v * (str_ends_with(strtoupper($v), 'G') ? 1024 : (str_ends_with(strtoupper($v), 'K') ? 1 / 1024 : 1));

        return (int) min($ke(ini_get('upload_max_filesize')), $ke(ini_get('post_max_size')));
    }
}
