<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\P3a;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class P3aController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('lihat-master');
        $p3a = P3a::with('kabupaten:id,nama', 'daerahIrigasi:id,nama')
            ->when($request->filled('kabupaten'), fn ($q) => $q->where('kabupaten_id', $request->integer('kabupaten')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('nama', 'like', '%'.$request->q.'%')->orWhere('ketua', 'like', '%'.$request->q.'%')))
            ->orderBy('kabupaten_id')->orderBy('nama')->paginate(50)->withQueryString();

        return view('master.p3a.index', ['p3a' => $p3a, 'kabupatenList' => Kabupaten::whereIn('id', P3a::select('kabupaten_id'))->orderBy('nama')->get()]);
    }

    public function create()
    {
        $this->authorize('kelola-master');

        return view('master.p3a.form', ['p3a' => new P3a(['jenis' => 'P3A'])] + $this->opsi());
    }

    public function store(Request $request)
    {
        $this->authorize('kelola-master');
        P3a::create($this->validasi($request));

        return redirect()->route('master.p3a.index')->with('ok', 'P3A ditambahkan.');
    }

    public function edit(P3a $p3a)
    {
        $this->authorize('kelola-master');

        return view('master.p3a.form', ['p3a' => $p3a] + $this->opsi());
    }

    public function update(Request $request, P3a $p3a)
    {
        $this->authorize('kelola-master');
        $p3a->update($this->validasi($request));

        return redirect()->route('master.p3a.index')->with('ok', 'P3A diperbarui.');
    }

    public function destroy(P3a $p3a)
    {
        $this->authorize('kelola-master');
        $p3a->delete();

        return back()->with('ok', 'P3A dihapus.');
    }

    private function validasi(Request $request): array
    {
        $request->merge(['no_hp' => normalisasi_hp($request->input('no_hp'))]);

        return $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(['P3A', 'GP3A', 'IP3A'])],
            'ketua' => ['nullable', 'string', 'max:255'],
            'no_hp' => ['nullable', 'string', 'max:20'],
            'kabupaten_id' => ['nullable', 'exists:kabupaten,id'],
            'daerah_irigasi_id' => ['nullable', 'exists:daerah_irigasi,id'],
        ]);
    }

    private function opsi(): array
    {
        return ['kabupatenList' => Kabupaten::orderBy('nama')->get(), 'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama'])];
    }
}
