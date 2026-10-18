<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\SubDaerahIrigasi;
use App\Services\FotoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Infrastruktur (bangunan & saluran) per D.I.: lokasi, kondisi fisik, dan foto kondisi. */
class BangunanController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $bangunan = Bangunan::with('daerahIrigasi:id,nama', 'subDaerahIrigasi:id,nama')->withCount('foto')
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('jenis'), fn ($q) => $q->where('jenis', $request->jenis))
            ->when($request->filled('kondisi'), fn ($q) => $request->kondisi === 'belum' ? $q->whereNull('kondisi') : $q->where('kondisi', $request->kondisi))
            ->when($request->titik === 'belum', fn ($q) => $q->whereNull('lat'))
            ->when($request->filled('q'), fn ($q) => $q->where('nama', 'like', '%'.$request->q.'%'))
            ->orderBy('daerah_irigasi_id')->orderBy('nama')->paginate(50)->withQueryString();

        $rekap = Bangunan::query()
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->selectRaw('kondisi, count(*) as n')->groupBy('kondisi')->pluck('n', 'kondisi');

        return view('master.bangunan.index', [
            'bangunan' => $bangunan,
            'rekap' => $rekap,
            'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('kelola-master');
        $b = new Bangunan(['daerah_irigasi_id' => $request->integer('di') ?: null, 'jenis' => 'bendung']);

        return view('master.bangunan.form', ['bangunan' => $b] + $this->opsi($b));
    }

    public function store(Request $request)
    {
        $this->authorize('kelola-master');
        $data = $this->validasi($request);
        $b = Bangunan::create(collect($data)->except('foto')->all());
        $this->foto->simpan($b, $request->file('foto', []), 'bangunan', $b->lat, $b->lng);

        return redirect()->route('master.bangunan.edit', $b)->with('ok', "{$b->nama} ditambahkan.");
    }

    public function edit(Bangunan $bangunan)
    {
        $this->authorize('kelola-master');
        $bangunan->load('foto', 'daerahIrigasi');

        return view('master.bangunan.form', ['bangunan' => $bangunan] + $this->opsi($bangunan));
    }

    public function update(Request $request, Bangunan $bangunan)
    {
        $this->authorize('kelola-master');
        $data = $this->validasi($request, $bangunan);
        $bangunan->update(collect($data)->except('foto')->all());
        $sisa = Bangunan::MAKS_FOTO - $bangunan->foto()->count();
        $n = $this->foto->simpan($bangunan, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'bangunan', $bangunan->lat, $bangunan->lng);

        return redirect()->route('master.bangunan.edit', $bangunan)->with('ok', "{$bangunan->nama} diperbarui".($n ? " (+$n foto)" : '').'.');
    }

    public function destroy(Bangunan $bangunan)
    {
        $this->authorize('kelola-master');
        $di = $bangunan->daerah_irigasi_id;
        $bangunan->delete();

        return redirect()->route('master.bangunan.index', ['di' => $di])->with('ok', 'Infrastruktur dihapus.');
    }

    public function hapusFoto(Bangunan $bangunan, Foto $foto)
    {
        $this->authorize('kelola-master');
        abort_unless($foto->fotoable_type === $bangunan->getMorphClass() && $foto->fotoable_id === $bangunan->id, 404);
        $foto->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    private function validasi(Request $request, ?Bangunan $b = null): array
    {
        $sisa = Bangunan::MAKS_FOTO - ($b?->foto()->count() ?? 0);

        $data = $request->validate([
            'daerah_irigasi_id' => ['required', 'exists:daerah_irigasi,id'],
            'sub_daerah_irigasi_id' => ['nullable', Rule::exists('sub_daerah_irigasi', 'id')->where('daerah_irigasi_id', $request->integer('daerah_irigasi_id'))],
            'nama' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(Bangunan::JENIS))],
            'lokasi' => ['nullable', 'string', 'max:255'],
            'kondisi' => ['nullable', Rule::in(array_keys(Bangunan::KONDISI))],
            'kondisi_per' => ['nullable', 'date', 'before_or_equal:today'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $sisa)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'sub_daerah_irigasi_id.exists' => 'Sub-D.I. tidak termasuk D.I. yang dipilih.',
            'foto.max' => $sisa > 0 ? "Sisa kuota foto $sisa." : 'Sudah ada '.Bangunan::MAKS_FOTO.' foto. Hapus foto lama dulu.',
            'foto.*.image' => 'File harus berupa gambar (JPG/PNG).',
            'lat.between' => 'Titik harus berada di wilayah NTT.',
            'lng.between' => 'Titik harus berada di wilayah NTT.',
        ]);
        // kondisi diisi tanpa tanggal → anggap dinilai hari ini
        if (! empty($data['kondisi']) && empty($data['kondisi_per']) && (! $b || $b->kondisi !== $data['kondisi'])) {
            $data['kondisi_per'] = today()->toDateString();
        }

        return $data;
    }

    private function opsi(Bangunan $b): array
    {
        return [
            'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama', 'lat', 'lng']),
            'subList' => SubDaerahIrigasi::orderBy('nama')->get(['id', 'nama', 'daerah_irigasi_id']),
        ];
    }
}
