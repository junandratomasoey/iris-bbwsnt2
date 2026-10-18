<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\SubDaerahIrigasi;
use App\Services\FotoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KejadianController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $kejadian = Kejadian::with(['kabupaten:id,nama,wilayah_satgas_id', 'kabupaten.wilayahSatgas:id,kode', 'daerahIrigasi:id,nama'])
            ->when($request->filled('status'), fn ($q) => $q->where('status_penanganan', $request->input('status')))
            ->when($request->filled('kabupaten'), fn ($q) => $q->where('kabupaten_id', $request->integer('kabupaten')))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis_kegagalan', $request->input('jenis')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('desa', 'like', '%'.$request->q.'%')->orWhere('kecamatan', 'like', '%'.$request->q.'%')->orWhere('pelapor_nama', 'like', '%'.$request->q.'%')))
            ->latest('tanggal_lapor')->latest('id')
            ->paginate(25)->withQueryString();

        $rekap = Kejadian::selectRaw('status_penanganan, count(*) jumlah, sum(luas_terdampak_ha) luas')->groupBy('status_penanganan')->get()->keyBy('status_penanganan');
        $kabupatenList = Kabupaten::whereHas('wilayahSatgas')->orWhereIn('id', Kejadian::select('kabupaten_id'))->orderBy('nama')->get(['id', 'nama']);

        return view('kejadian.index', compact('kejadian', 'rekap', 'kabupatenList'));
    }

    public function create()
    {
        $this->authorize('create', Kejadian::class);

        return view('kejadian.form', ['kejadian' => new Kejadian(['tanggal_lapor' => today(), 'status_kegagalan' => 'Potensi'])] + $this->opsi());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Kejadian::class);
        $data = $this->validasi($request);
        $data['user_id'] = $request->user()->id;
        $data['status_penanganan'] = 'Perlu Penanganan';
        $data['pelapor_nama'] ??= $request->user()->name;
        $data['pelapor_hp'] = normalisasi_hp($data['pelapor_hp'] ?? $request->user()->no_hp);
        $data['sumber_data'] ??= 'Aplikasi IRIS';
        $kejadian = Kejadian::create($data);
        $this->foto->simpan($kejadian, $request->file('foto', []), 'kejadian', $kejadian->lat, $kejadian->lng);

        return redirect()->route('kejadian.show', $kejadian)->with('ok', 'Kejadian dilaporkan.');
    }

    public function show(Kejadian $kejadian)
    {
        $kejadian->load('kabupaten.wilayahSatgas', 'daerahIrigasi', 'subDaerahIrigasi', 'user', 'foto');

        return view('kejadian.show', compact('kejadian'));
    }

    public function edit(Kejadian $kejadian)
    {
        $this->authorize('update', $kejadian);
        $kejadian->load('foto');

        return view('kejadian.form', compact('kejadian') + $this->opsi());
    }

    public function update(Request $request, Kejadian $kejadian)
    {
        $this->authorize('update', $kejadian);
        $data = $this->validasi($request);
        $data['pelapor_hp'] = normalisasi_hp($data['pelapor_hp'] ?? null);
        $kejadian->update($data);
        $this->foto->simpan($kejadian, $request->file('foto', []), 'kejadian', $kejadian->lat, $kejadian->lng);

        return redirect()->route('kejadian.show', $kejadian)->with('ok', 'Kejadian diperbarui.');
    }

    public function penanganan(Request $request, Kejadian $kejadian)
    {
        $this->authorize('tangani', $kejadian);
        $data = $request->validate([
            'status_penanganan' => ['required', Rule::in(array_keys(Kejadian::STATUS))],
            'luas_tertangani_ha' => ['required', 'numeric', 'min:0', 'lte:'.(float) $kejadian->luas_terdampak_ha],
            'solusi' => ['nullable', 'string', 'max:2000'],
        ], ['luas_tertangani_ha.lte' => 'Luas tertangani tidak boleh melebihi luas terdampak ('.angka($kejadian->luas_terdampak_ha, 2).' Ha).']);
        $kejadian->update($data);

        return back()->with('ok', 'Status penanganan diperbarui.');
    }

    public function destroy(Kejadian $kejadian)
    {
        $this->authorize('delete', $kejadian);
        $kejadian->foto->each->delete();
        $kejadian->delete();

        return redirect()->route('kejadian.index')->with('ok', 'Kejadian dihapus.');
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'tanggal_lapor' => ['required', 'date', 'before_or_equal:today'],
            'daerah_irigasi_id' => ['nullable', 'exists:daerah_irigasi,id'],
            'sub_daerah_irigasi_id' => ['nullable', 'exists:sub_daerah_irigasi,id'],
            'kabupaten_id' => ['required', 'exists:kabupaten,id'],
            'kecamatan' => ['nullable', 'string', 'max:100'],
            'desa' => ['nullable', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'numeric', 'between:118,126'],
            'jenis_kegagalan' => ['required', Rule::in(Kejadian::JENIS)],
            'penyebab' => ['nullable', 'string', 'max:255'],
            'status_kegagalan' => ['required', Rule::in(['Potensi', 'Terjadi'])],
            'luas_terdampak_ha' => ['required', 'numeric', 'min:0', 'max:20000'],
            'sumber_air' => ['nullable', 'string', 'max:1000'],
            'solusi' => ['nullable', 'string', 'max:2000'],
            'p3a' => ['nullable', 'string', 'max:255'],
            'pelapor_nama' => ['nullable', 'string', 'max:255'],
            'pelapor_hp' => ['nullable', 'string', 'max:20'],
            'catatan' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'array', 'max:5'],
            'foto.*' => ['image', 'max:8192'],
        ], [], ['kabupaten_id' => 'kabupaten', 'luas_terdampak_ha' => 'luas terdampak', 'jenis_kegagalan' => 'jenis kejadian']);
    }

    private function opsi(): array
    {
        return [
            'kabupatenList' => Kabupaten::orderBy('nama')->get(['id', 'nama']),
            'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama', 'kabupaten_id']),
            'subList' => SubDaerahIrigasi::orderBy('nama')->get(['id', 'nama', 'daerah_irigasi_id']),
        ];
    }
}
