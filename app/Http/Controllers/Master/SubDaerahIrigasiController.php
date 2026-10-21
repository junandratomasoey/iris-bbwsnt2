<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\Kabupaten;
use App\Models\SubDaerahIrigasi;
use App\Services\FotoService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SubDaerahIrigasiController extends Controller
{
    public function create(DaerahIrigasi $di)
    {
        $this->authorize('kelola-master');

        return view('master.sub.form', ['sub' => new SubDaerahIrigasi(['daerah_irigasi_id' => $di->id, 'kabupaten_id' => $di->kabupaten_id]), 'di' => $di, 'kabupatenList' => Kabupaten::orderBy('nama')->get()]);
    }

    public function store(Request $request, DaerahIrigasi $di)
    {
        $this->authorize('kelola-master');
        $di->subDaerahIrigasi()->create($this->validasi($request));

        return redirect()->route('master.daerah-irigasi.show', $di)->with('ok', 'Sub-D.I. ditambahkan.');
    }

    public function edit(SubDaerahIrigasi $sub)
    {
        $this->authorize('kelola-master');
        $sub->load('daerahIrigasi', 'fotoProfil');

        return view('master.sub.form', ['sub' => $sub, 'di' => $sub->daerahIrigasi, 'kabupatenList' => Kabupaten::orderBy('nama')->get()]);
    }

    public function update(Request $request, SubDaerahIrigasi $sub)
    {
        $this->authorize('kelola-master');
        $sub->update($this->validasi($request));

        return redirect()->route('master.daerah-irigasi.show', $sub->daerah_irigasi_id)->with('ok', "Sub-D.I. {$sub->nama} diperbarui.");
    }

    public function destroy(SubDaerahIrigasi $sub)
    {
        $this->authorize('kelola-master');
        abort_if($sub->laporanHarian()->exists(), 422, 'Sub-D.I. ini sudah punya laporan harian sehingga tidak bisa dihapus.');
        $diId = $sub->daerah_irigasi_id;
        $sub->delete();

        return redirect()->route('master.daerah-irigasi.show', $diId)->with('ok', 'Sub-D.I. dihapus.');
    }

    /** Foto profil Sub-D.I. (tampil di peta publik). */
    public function fotoStore(Request $request, SubDaerahIrigasi $sub, FotoService $foto)
    {
        $this->authorize('kelola-master');
        $sisa = SubDaerahIrigasi::MAKS_FOTO_PROFIL - $sub->fotoProfil()->count();
        $data = $request->validate([
            'foto' => ['required', 'array', 'min:1', 'max:'.max(0, $sisa)],
            'foto.*' => ['image', 'max:8192'],
            'judul' => ['nullable', 'string', 'max:150'],
            'diambil_pada' => ['nullable', 'date', 'before_or_equal:today'],
        ], [
            'foto.required' => 'Pilih minimal satu foto.',
            'foto.max' => $sisa > 0 ? "Sisa tempat foto $sisa." : 'Sudah ada '.SubDaerahIrigasi::MAKS_FOTO_PROFIL.' foto. Hapus salah satu dulu.',
            'foto.*.image' => 'File harus berupa gambar (JPG/PNG).',
        ]);
        $urutan = (int) $sub->fotoProfil()->max('urutan') + 1;
        $n = 0;
        foreach ($request->file('foto', []) as $f) {
            $n += $foto->simpan($sub, [$f], 'profil-sub', $sub->lat, $sub->lng, 'profil', [
                'judul' => $data['judul'] ?? null, 'diambil_pada' => $data['diambil_pada'] ?? null, 'urutan' => $urutan++,
            ]);
        }

        return back()->with('ok', "$n foto Sub-D.I. ditambahkan.");
    }

    public function fotoDestroy(SubDaerahIrigasi $sub, Foto $foto)
    {
        $this->authorize('kelola-master');
        abort_unless($foto->fotoable_type === $sub->getMorphClass() && $foto->fotoable_id === $sub->id, 404);
        $foto->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    private function validasi(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'luas_ha' => ['nullable', 'numeric', 'min:0'],
            'luas_fungsional_ha' => ['nullable', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'kecamatan' => ['nullable', 'string', 'max:255'],
            'desa' => ['nullable', 'string', 'max:500'],
            'saluran' => ['nullable', 'string', 'max:255'],
            'panjang_saluran_m' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'kabupaten_id' => ['nullable', 'exists:kabupaten,id'],
            'lat' => ['nullable', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'numeric', 'between:118,126'],
            'geometri' => ['nullable', 'json'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ], [], ['luas_ha' => 'luas baku', 'luas_fungsional_ha' => 'luas fungsional', 'panjang_saluran_m' => 'panjang saluran']);
        if (! $request->has('geometri')) {
            unset($data['geometri']);

            return $data;
        }
        $g = filled($data['geometri'] ?? null) ? json_decode($data['geometri'], true) : null;
        if ($g !== null && ! in_array($g['type'] ?? null, ['Polygon', 'MultiPolygon'], true)) {
            throw ValidationException::withMessages(['geometri' => 'Batas areal harus berupa poligon.']);
        }
        $data['geometri'] = $g;
        // titik Sub-D.I. belum diisi → pakai titik tengah batas areal
        if ($g && ! filled($data['lat'] ?? null)) {
            $titik = collect($g['type'] === 'Polygon' ? $g['coordinates'][0] : collect($g['coordinates'])->flatMap(fn ($p) => $p[0]));
            // tengah kotak batas (tidak tergeser titik penutup poligon yang berulang)
            $data['lng'] = round(($titik->min(fn ($c) => $c[0]) + $titik->max(fn ($c) => $c[0])) / 2, 7);
            $data['lat'] = round(($titik->min(fn ($c) => $c[1]) + $titik->max(fn ($c) => $c[1])) / 2, 7);
        }

        return $data;
    }
}
