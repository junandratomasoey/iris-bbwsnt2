<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\SubDaerahIrigasi;
use Illuminate\Http\Request;

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
        $sub->load('daerahIrigasi');

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

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'luas_ha' => ['nullable', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'kabupaten_id' => ['nullable', 'exists:kabupaten,id'],
            'lat' => ['nullable', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'numeric', 'between:118,126'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
