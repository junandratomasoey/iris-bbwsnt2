<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use App\Services\FotoService;
use App\Support\Role;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanHarianController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $laporan = $this->query($request)
            ->with(['subDaerahIrigasi.daerahIrigasi:id,nama', 'user:id,name,no_hp', 'foto'])
            ->withCount('foto')
            ->latest('tanggal')->latest('id')
            ->paginate(25)->withQueryString();

        $diList = DaerahIrigasi::when($user->subIdsTerjangkau() !== null, fn ($q) => $q->whereHas('subDaerahIrigasi', fn ($s) => $s->whereIn('id', $user->subIdsTerjangkau())))
            ->orderBy('nama')->get(['id', 'nama']);

        $menunggu = $user->can('verifikasi') ? LaporanHarian::where('status_verifikasi', 'menunggu')
            ->when($user->subIdsTerjangkau() !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $user->subIdsTerjangkau()))->count() : 0;

        return view('laporan.index', compact('laporan', 'diList', 'menunggu'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', LaporanHarian::class);
        $subs = $this->subPelaporan($request);
        abort_if($subs->isEmpty(), 403, 'Anda belum ditugaskan ke Sub-D.I. mana pun. Hubungi Admin OP.');

        $laporan = new LaporanHarian([
            'tanggal' => today(),
            'sub_daerah_irigasi_id' => $request->integer('sub') ?: ($subs->count() === 1 ? $subs->first()->id : null),
        ]);
        // isian awal dari laporan terakhir sub tersebut agar petugas cukup mengubah yang berbeda
        $sebelumnya = $laporan->sub_daerah_irigasi_id
            ? LaporanHarian::where('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id)->latest('tanggal')->first()
            : null;
        if ($sebelumnya) {
            $laporan->fill($sebelumnya->only(['debit_intake_lps', 'tma_cm', 'kondisi_saluran', 'bukaan_pintu_persen', 'fase_tanaman', 'luas_tanam_ha', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha', 'luas_puso_ha']));
        }

        return view('laporan.form', compact('laporan', 'subs', 'sebelumnya'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', LaporanHarian::class);
        $data = $this->validasi($request);
        abort_unless($request->user()->bolehLapor((int) $data['sub_daerah_irigasi_id']), 403, 'Sub-D.I. ini bukan wilayah tugas Anda.');

        $data['user_id'] = $request->user()->id;
        $data['mt'] = $this->mt($data);
        $laporan = LaporanHarian::create($data);
        $this->foto->simpan($laporan, $request->file('foto', []), 'laporan', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan.show', $laporan)->with('ok', 'Laporan '.$laporan->tanggal->translatedFormat('d M Y').' terkirim.')->with('hapus_draft', true);
    }

    public function show(LaporanHarian $laporan)
    {
        $this->authorize('view', $laporan);
        $laporan->load('subDaerahIrigasi.daerahIrigasi.kabupaten', 'user', 'verifikator', 'foto');
        $riwayat = LaporanHarian::where('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id)
            ->where('tanggal', '<=', $laporan->tanggal)->latest('tanggal')->limit(14)->get()->reverse()->values();

        return view('laporan.show', compact('laporan', 'riwayat'));
    }

    public function edit(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $laporan->load('foto');
        $subs = $this->subPelaporan($request)->push($laporan->subDaerahIrigasi)->unique('id');
        $sebelumnya = null;

        return view('laporan.form', compact('laporan', 'subs', 'sebelumnya'));
    }

    public function update(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $data = $this->validasi($request, $laporan);
        if ((int) $data['sub_daerah_irigasi_id'] !== $laporan->sub_daerah_irigasi_id) {
            abort_unless($request->user()->bolehLapor((int) $data['sub_daerah_irigasi_id']), 403);
        }
        $data['mt'] = $this->mt($data);
        if ($laporan->status_verifikasi === 'perbaikan' && $laporan->user_id === $request->user()->id) {
            $data['status_verifikasi'] = 'menunggu';
        }
        $laporan->update($data);
        $sisa = 5 - $laporan->foto()->count();
        $this->foto->simpan($laporan, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'laporan', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan.show', $laporan)->with('ok', 'Laporan diperbarui.');
    }

    public function destroy(LaporanHarian $laporan)
    {
        $this->authorize('delete', $laporan);
        $laporan->foto->each->delete();
        $laporan->delete();

        return redirect()->route('laporan.index')->with('ok', 'Laporan dihapus.');
    }

    public function hapusFoto(LaporanHarian $laporan, Foto $foto)
    {
        $this->authorize('update', $laporan);
        abort_unless($foto->fotoable_type === $laporan->getMorphClass() && $foto->fotoable_id === $laporan->id, 404);
        $foto->delete();

        return back()->with('ok', 'Foto dihapus.');
    }

    public function verifikasi(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('verify', $laporan);
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['disetujui', 'perbaikan'])],
            'catatan_verifikasi' => ['nullable', 'required_if:keputusan,perbaikan', 'string', 'max:1000'],
        ], ['catatan_verifikasi.required_if' => 'Tulis apa yang perlu diperbaiki petugas.']);

        $laporan->update([
            'status_verifikasi' => $data['keputusan'],
            'catatan_verifikasi' => $data['catatan_verifikasi'] ?? null,
            'diverifikasi_oleh' => $request->user()->id,
            'diverifikasi_pada' => now(),
        ]);

        $lanjut = $request->input('lanjut')
            ? LaporanHarian::where('status_verifikasi', 'menunggu')
                ->when($request->user()->subIdsTerjangkau() !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $request->user()->subIdsTerjangkau()))
                ->oldest('tanggal')->first()
            : null;

        return ($lanjut ? redirect()->route('laporan.show', $lanjut) : back())
            ->with('ok', $data['keputusan'] === 'disetujui' ? 'Laporan disetujui.' : 'Laporan dikembalikan untuk perbaikan.');
    }

    /** Setujui beberapa laporan sekaligus dari daftar. Hanya yang masih "menunggu" dan dalam wilayah verifikator. */
    public function verifikasiMassal(Request $request)
    {
        abort_unless($request->user()->can('verifikasi'), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer']], ['ids.required' => 'Pilih minimal satu laporan.']);

        $n = 0;
        LaporanHarian::whereIn('id', $data['ids'])->where('status_verifikasi', 'menunggu')->get()
            ->each(function (LaporanHarian $l) use ($request, &$n) {
                if ($request->user()->cannot('verify', $l)) {
                    return;
                }
                $l->update(['status_verifikasi' => 'disetujui', 'catatan_verifikasi' => null, 'diverifikasi_oleh' => $request->user()->id, 'diverifikasi_pada' => now()]);
                $n++;
            });

        return back()->with('ok', $n ? "$n laporan disetujui." : 'Tidak ada laporan yang bisa disetujui.');
    }

    public function ekspor(Request $request): StreamedResponse
    {
        $nama = 'laporan-harian-'.now()->format('Ymd-His').'.csv';
        $q = $this->query($request)->with(['subDaerahIrigasi.daerahIrigasi.kabupaten', 'user:id,name'])->orderBy('tanggal');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Tanggal', 'Kabupaten', 'D.I.', 'Sub-D.I.', 'MT', 'Debit intake (L/dt)', 'TMA (cm)', 'Kondisi saluran', 'Bukaan pintu (%)', 'Fase', 'Luas tanam (Ha)', 'Luas hijau (Ha)', 'Luas kekeringan (Ha)', 'Luas panen (Ha)', 'Luas puso (Ha)', 'Status', 'Kendala', 'Pelapor', 'Verifikasi', 'Lat', 'Lng'], ';');
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    $s = $l->subDaerahIrigasi;
                    fputcsv($out, [
                        $l->tanggal->format('Y-m-d'), $s->daerahIrigasi->kabupaten?->nama, $s->daerahIrigasi->nama, $s->nama, $l->mt,
                        $l->debit_intake_lps, $l->tma_cm, $l->kondisi_saluran, $l->bukaan_pintu_persen, $l->fase_tanaman,
                        $l->luas_tanam_ha, $l->luas_hijau_ha, $l->luas_kering_ha, $l->luas_panen_ha, $l->luas_puso_ha,
                        StatusKondisi::label($l->status()), $l->kendala, $l->user?->name, $l->status_verifikasi, $l->lat, $l->lng,
                    ], ';');
                }
            });
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---------------------------------------------------------------

    private function query(Request $request)
    {
        $user = $request->user();
        $subIds = $user->subIdsTerjangkau();

        return LaporanHarian::query()
            ->when($subIds !== null, fn ($q) => $q->whereIn('sub_daerah_irigasi_id', $subIds))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->date('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->date('sampai')))
            ->when($request->filled('di'), fn ($q) => $q->whereHas('subDaerahIrigasi', fn ($s) => $s->where('daerah_irigasi_id', $request->integer('di'))))
            ->when($request->filled('verifikasi'), fn ($q) => $q->where('status_verifikasi', $request->input('verifikasi')))
            ->when($request->boolean('milik_saya'), fn ($q) => $q->where('user_id', $user->id));
    }

    private function subPelaporan(Request $request)
    {
        $ids = $request->user()->subIdsPelaporan();

        return SubDaerahIrigasi::with('daerahIrigasi:id,nama,lat,lng')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('daerah_irigasi_id')->orderBy('nama')->get();
    }

    private function validasi(Request $request, ?LaporanHarian $laporan = null): array
    {
        $pengelola = $request->user()->hasRole(...Role::PENGELOLA);
        $batasMundur = $pengelola ? '2000-01-01' : today()->subDays(7)->toDateString();
        $maksFoto = 5 - ($laporan?->foto()->count() ?? 0);

        return $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$batasMundur],
            'sub_daerah_irigasi_id' => [
                'required', 'exists:sub_daerah_irigasi,id',
                Rule::unique('laporan_harian')->where(fn ($q) => $q->where('tanggal', rescue(fn () => Carbon::parse($request->input('tanggal'))->toDateString(), (string) $request->input('tanggal'), false)))->ignore($laporan?->id),
            ],
            'debit_intake_lps' => ['required', 'numeric', 'min:0', 'max:100000'],
            'tma_cm' => ['required', 'numeric', 'min:-1000', 'max:100000'],
            'kondisi_saluran' => ['required', Rule::in(array_keys(LaporanHarian::KONDISI))],
            'bukaan_pintu_persen' => ['nullable', 'integer', 'between:0,100'],
            'fase_tanaman' => ['nullable', Rule::in(array_keys(LaporanHarian::FASE))],
            'luas_tanam_ha' => ['required', 'numeric', 'min:0', 'max:20000'],
            'luas_hijau_ha' => ['required', 'numeric', 'min:0', 'lte:luas_tanam_ha'],
            'luas_kering_ha' => ['required', 'numeric', 'min:0', 'lte:luas_tanam_ha'],
            'luas_panen_ha' => ['required', 'numeric', 'min:0', 'max:20000'],
            'luas_puso_ha' => ['required', 'numeric', 'min:0', 'lte:luas_tanam_ha'],
            'kendala' => ['nullable', 'string', 'max:2000'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $maksFoto)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'sub_daerah_irigasi_id.unique' => 'Sub-D.I. ini sudah punya laporan pada tanggal tersebut. Buka laporan itu untuk mengubahnya.',
            'tanggal.after_or_equal' => 'Laporan hanya bisa diisi untuk 7 hari terakhir.',
            'lte' => ':attribute tidak boleh lebih besar dari luas tanam.',
            'lat.between' => 'Lintang harus antara -12 dan -7 (wilayah NTT). Periksa koordinat.',
            'lng.between' => 'Bujur harus antara 118 dan 126 (wilayah NTT). Periksa koordinat.',
            'lat.required_with' => 'Isi lintang dan bujur sekaligus.',
            'lng.required_with' => 'Isi lintang dan bujur sekaligus.',
            'foto.max' => 'Maksimal 5 foto per laporan.',
        ], [
            'sub_daerah_irigasi_id' => 'Sub-D.I.', 'debit_intake_lps' => 'debit intake', 'tma_cm' => 'TMA',
            'luas_tanam_ha' => 'luas tanam', 'luas_hijau_ha' => 'luas hijau', 'luas_kering_ha' => 'luas kekeringan',
            'luas_panen_ha' => 'luas panen', 'luas_puso_ha' => 'luas puso', 'kondisi_saluran' => 'kondisi saluran',
        ]);
    }

    private function mt(array $data): ?int
    {
        $di = SubDaerahIrigasi::find($data['sub_daerah_irigasi_id'])?->daerahIrigasi;

        return $di?->mtBerjalan(Carbon::parse($data['tanggal']))?->mt;
    }
}
