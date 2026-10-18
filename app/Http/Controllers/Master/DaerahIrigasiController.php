<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\P3a;
use App\Services\FotoService;
use App\Services\KondisiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DaerahIrigasiController extends Controller
{
    public function index(Request $request, KondisiService $kondisi)
    {
        $ringkasan = $kondisi->ringkasanDi(today());
        $di = DaerahIrigasi::with('kabupaten.wilayahSatgas')->withCount(['subDaerahIrigasi', 'petugas', 'bangunan', 'fotoProfil'])
            ->when($request->filled('q'), fn ($q) => $q->where('nama', 'like', '%'.$request->q.'%'))
            ->orderBy('kode')->get();

        return view('master.di.index', compact('di', 'ringkasan'));
    }

    public function show(DaerahIrigasi $di, KondisiService $kondisi)
    {
        $di->load(['fotoProfil', 'kabupaten.wilayahSatgas', 'subDaerahIrigasi.petugas:id,name,no_hp,jabatan', 'bangunan', 'jadwalTanam',
            'petugas' => fn ($q) => $q->orderByRaw("CASE jabatan WHEN 'Manager' THEN 0 WHEN 'POB' THEN 1 ELSE 2 END")->orderBy('name')]);
        $ring = $kondisi->ringkasanDi(today())->get($di->id);
        $laporanTerkini = $kondisi->laporanTerkini(today(), $di->subDaerahIrigasi->pluck('id')->all());
        $p3a = P3a::where('daerah_irigasi_id', $di->id)->orWhere('kabupaten_id', $di->kabupaten_id)->orderBy('nama')->limit(50)->get();
        $tren = LaporanHarian::whereIn('sub_daerah_irigasi_id', $di->subDaerahIrigasi->pluck('id'))
            ->where('tanggal', '>=', today()->subDays(29))
            ->selectRaw('tanggal, sum(debit_intake_lps) debit, sum(luas_kering_ha) kering, sum(luas_tanam_ha) tanam, count(*) n')
            ->groupBy('tanggal')->orderBy('tanggal')->get();

        return view('master.di.show', compact('di', 'ring', 'laporanTerkini', 'p3a', 'tren'));
    }

    public function create()
    {
        $this->authorize('kelola-master');

        return view('master.di.form', ['di' => new DaerahIrigasi(['kewenangan' => 'Pusat']), 'kabupatenList' => Kabupaten::orderBy('nama')->get()]);
    }

    public function store(Request $request)
    {
        $this->authorize('kelola-master');
        $di = DaerahIrigasi::create($this->validasi($request));

        return redirect()->route('master.daerah-irigasi.show', $di)->with('ok', 'D.I. ditambahkan.');
    }

    public function edit(DaerahIrigasi $di)
    {
        $this->authorize('kelola-master');

        return view('master.di.form', ['di' => $di, 'kabupatenList' => Kabupaten::orderBy('nama')->get()]);
    }

    public function update(Request $request, DaerahIrigasi $di)
    {
        $this->authorize('kelola-master');
        $di->update($this->validasi($request, $di));

        return redirect()->route('master.daerah-irigasi.show', $di)->with('ok', 'Data D.I. diperbarui.');
    }

    public function destroy(DaerahIrigasi $di)
    {
        $this->authorize('kelola-master');
        abort_if($di->laporanHarian()->exists(), 422, 'D.I. ini sudah punya laporan harian sehingga tidak bisa dihapus.');
        $di->delete();

        return redirect()->route('master.daerah-irigasi.index')->with('ok', 'D.I. dihapus.');
    }

    // ---------- foto profil (kondisi awal) ----------

    public function fotoProfilStore(Request $request, DaerahIrigasi $di, FotoService $foto)
    {
        $this->authorize('kelola-master');
        $sisa = DaerahIrigasi::MAKS_FOTO_PROFIL - $di->fotoProfil()->count();
        $data = $request->validate([
            'foto' => ['required', 'array', 'min:1', 'max:'.max(0, $sisa)],
            'foto.*' => ['image', 'max:8192'],
            'judul' => ['nullable', 'string', 'max:150'],
            'diambil_pada' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'foto.required' => 'Pilih minimal satu foto.',
            'foto.max' => $sisa > 0 ? "Sisa tempat foto profil $sisa." : 'Sudah ada '.DaerahIrigasi::MAKS_FOTO_PROFIL.' foto profil. Hapus salah satu dulu.',
            'foto.*.image' => 'File harus berupa gambar (JPG/PNG).',
        ]);
        $urutan = (int) $di->fotoProfil()->max('urutan') + 1;
        $n = 0;
        foreach ($request->file('foto', []) as $f) {
            $n += $foto->simpan($di, [$f], 'profil-di', null, null, 'profil', [
                'judul' => $data['judul'] ?? null, 'diambil_pada' => $data['diambil_pada'] ?? null, 'urutan' => $urutan++,
            ]);
        }

        return back()->with('ok', "$n foto profil ditambahkan.");
    }

    public function fotoProfilUpdate(Request $request, DaerahIrigasi $di, Foto $foto)
    {
        $this->authorize('kelola-master');
        $this->milikDi($di, $foto);
        $data = $request->validate([
            'judul' => ['nullable', 'string', 'max:150'],
            'diambil_pada' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $foto->update($data);
        if ($request->boolean('utama')) { // jadikan foto pertama, sisanya bergeser
            $di->fotoProfil()->get()->reject(fn ($f) => $f->id === $foto->id)->values()
                ->each(fn ($f, $i) => $f->update(['urutan' => $i + 1]));
            $foto->update(['urutan' => 0]);
        }

        return back()->with('ok', 'Foto profil diperbarui.');
    }

    public function fotoProfilDestroy(DaerahIrigasi $di, Foto $foto)
    {
        $this->authorize('kelola-master');
        $this->milikDi($di, $foto);
        $foto->delete();

        return back()->with('ok', 'Foto profil dihapus.');
    }

    private function milikDi(DaerahIrigasi $di, Foto $foto): void
    {
        abort_unless($foto->fotoable_type === $di->getMorphClass() && $foto->fotoable_id === $di->id && $foto->keterangan === 'profil', 404);
    }

    private function validasi(Request $request, ?DaerahIrigasi $di = null): array
    {
        return $request->validate([
            'kode' => ['required', 'string', 'max:20', Rule::unique('daerah_irigasi')->ignore($di?->id)],
            'nama' => ['required', 'string', 'max:255'],
            'kabupaten_id' => ['nullable', 'exists:kabupaten,id'],
            'kewenangan' => ['required', 'string', 'max:50'],
            'luas_permen_ha' => ['nullable', 'numeric', 'min:0'],
            'luas_baku_ha' => ['nullable', 'numeric', 'min:0'],
            'luas_potensial_ha' => ['nullable', 'numeric', 'min:0'],
            'luas_fungsional_ha' => ['nullable', 'numeric', 'min:0'],
            'sumber_air' => ['nullable', 'string', 'max:255'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'numeric', 'between:118,126'],
        ]);
    }
}
