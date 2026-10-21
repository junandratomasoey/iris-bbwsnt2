<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\LaporanHarian;
use App\Models\LaporanPetak;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Services\FotoService;
use App\Services\PintuService;
use App\Support\KodeIsian;
use App\Support\StatusKondisi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LaporanHarianController extends Controller
{
    public function __construct(private FotoService $foto) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $laporan = $this->query($request)
            ->with(['subDaerahIrigasi.daerahIrigasi:id,nama', 'user:id,name,no_hp', 'foto', 'petak.pintu:id,kode,nama'])
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

        $subId = $request->integer('sub') ?: ($subs->count() === 1 ? $subs->first()->id : null);
        $laporan = new LaporanHarian([
            'tanggal' => today(), 'jam' => now()->format('H:i'),
            'jenis' => today()->isMonday() ? 'rutin' : 'insidental',
            'sub_daerah_irigasi_id' => $subId,
            'mt' => $subId ? $subs->firstWhere('id', $subId)?->daerahIrigasi?->mtBerjalan(today())?->mt : null,
        ]);
        $laporan->setRelation('petak', collect());

        return view('laporan.form', $this->dataForm($request, $laporan, $subs));
    }

    public function store(Request $request)
    {
        $this->authorize('create', LaporanHarian::class);
        $u = $request->user();
        [$data, $petak, $baca] = $this->validasi($request, $u);
        abort_unless($u->bolehLapor((int) $data['sub_daerah_irigasi_id']), 403, 'Sub-D.I. ini bukan wilayah tugas Anda.');

        $laporan = DB::transaction(function () use ($data, $petak, $baca, $u) {
            $laporan = LaporanHarian::create($data + ['user_id' => $u->id]);
            $this->simpanPetak($laporan, $petak);
            $this->simpanPintu($laporan, $baca);

            return $laporan;
        });
        $this->foto->simpan($laporan, $request->file('foto', []), 'laporan', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan.show', $laporan)->with('ok', 'Laporan PPA '.$laporan->tanggal->translatedFormat('d M Y').' terkirim.')->with('hapus_draft', true);
    }

    public function show(LaporanHarian $laporan)
    {
        $this->authorize('view', $laporan);
        $laporan->load('subDaerahIrigasi.daerahIrigasi.kabupaten', 'user', 'verifikator', 'foto', 'petak.pintu', 'sesiPintu.pembacaan.pintu');
        $riwayat = LaporanHarian::where('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id)->where('user_id', $laporan->user_id)
            ->where('tanggal', '<=', $laporan->tanggal)->latest('tanggal')->limit(14)->get()->reverse()->values();

        return view('laporan.show', compact('laporan', 'riwayat'));
    }

    public function edit(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $laporan->load('foto', 'petak.pintu', 'sesiPintu.pembacaan', 'user', 'verifikator');
        $subs = $this->subPelaporan($request, true)->push($laporan->subDaerahIrigasi)->unique('id')->values();

        return view('laporan.form', $this->dataForm($request, $laporan, $subs));
    }

    public function update(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $u = $request->user();
        [$data, $petak, $baca] = $this->validasi($request, $u, $laporan);
        if ((int) $data['sub_daerah_irigasi_id'] !== $laporan->sub_daerah_irigasi_id) {
            abort_unless($u->bolehLapor((int) $data['sub_daerah_irigasi_id'])
                || ($u->boleh('kelola-laporan') && $u->bolehLihatSub((int) $data['sub_daerah_irigasi_id'])), 403);
        }
        if ($laporan->status_verifikasi === 'perbaikan' && $laporan->user_id === $u->id) {
            $data['status_verifikasi'] = 'menunggu';
        }
        DB::transaction(function () use ($laporan, $data, $petak, $baca) {
            $laporan->update($data);
            $this->simpanPetak($laporan, $petak);
            $this->simpanPintu($laporan, $baca);
        });
        $sisa = 5 - $laporan->foto()->count();
        $this->foto->simpan($laporan, array_slice($request->file('foto', []), 0, max(0, $sisa)), 'laporan', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan.show', $laporan)->with('ok', 'Laporan diperbarui.');
    }

    public function destroy(LaporanHarian $laporan)
    {
        $this->authorize('delete', $laporan);
        $laporan->sesiPintu()->get()->each->delete();
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
        $nama = 'laporan-ppa-'.now()->format('Ymd-His').'.csv';
        $q = $this->query($request)->with(['subDaerahIrigasi.daerahIrigasi.kabupaten', 'user:id,name', 'petak.pintu:id,kode'])->orderBy('tanggal');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Tanggal', 'Jam', 'Jenis', 'Kabupaten', 'D.I.', 'Sub-D.I.', 'Petak tersier', 'MT', 'Cuaca', 'Hujan 7 hari', 'Tindakan pintu',
                'Luas baku (Ha)', 'Luas tanam (Ha)', 'Jenis tanaman', 'Fase', 'Umur (HST)', 'Kecukupan air', 'Luas kering (Ha)', 'Luas panen (Ha)', 'Luas puso (Ha)',
                'Status petak', 'Keterangan petak', 'Status laporan', 'Permasalahan', 'Usulan', 'Pelapor', 'Verifikasi', 'Lat', 'Lng'], ';');
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    $s = $l->subDaerahIrigasi;
                    $baris = $l->petak->isNotEmpty() ? $l->petak : collect([null]);
                    foreach ($baris as $b) { // satu baris CSV per petak tersier
                        fputcsv($out, [
                            $l->tanggal->format('Y-m-d'), $l->jam ? substr($l->jam, 0, 5) : null, $l->jenis_label, $s->daerahIrigasi->kabupaten?->nama, $s->daerahIrigasi->nama, $s->nama,
                            $b?->pintu?->kode ?? ($b ? 'Seluruh Sub-D.I.' : null), $l->mt, KodeIsian::CUACA[$l->cuaca] ?? null, KodeIsian::HUJAN_7_HARI[$l->hujan_7hari] ?? null,
                            KodeIsian::TINDAKAN_PINTU[$l->tindakan_operasi] ?? null,
                            $b?->luas_baku_ha, $b?->luas_tanam_ha ?? $l->luas_tanam_ha, $b?->jenis_tanaman, $b?->fase, $b?->umur_hst, $b?->kecukupan_air,
                            $b?->luas_kering_ha ?? $l->luas_kering_ha, $b?->luas_panen_ha ?? $l->luas_panen_ha, $b?->luas_puso_ha ?? $l->luas_puso_ha,
                            $b ? StatusKondisi::label($b->status ?? $b->hitungStatus()) : null, $b?->keterangan,
                            StatusKondisi::label($l->status()), $l->kendala, $l->usulan, $l->user?->name, $l->status_verifikasi, $l->lat, $l->lng,
                        ], ';');
                    }
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

    private function subPelaporan(Request $request, bool $ubah = false)
    {
        $u = $request->user();
        // pengelola laporan boleh memindahkan laporan ke Sub-D.I. mana pun dalam jangkauannya
        $ids = $ubah && $u->boleh('kelola-laporan') ? $u->subIdsTerjangkau() : $u->subIdsPelaporan();

        return SubDaerahIrigasi::with('daerahIrigasi:id,nama,lat,lng,kabupaten_id', 'daerahIrigasi.kabupaten:id,nama')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('daerah_irigasi_id')->orderBy('nama')->get();
    }

    /** Data form: pilihan petak & pintu per Sub-D.I., isian awal dari laporan sebelumnya. */
    private function dataForm(Request $request, LaporanHarian $laporan, $subs): array
    {
        $u = $request->user();
        $lamaPetak = $laporan->petak->pluck('pintu_air_id')->filter()->all();
        $lamaPintu = $laporan->sesiPintu?->pembacaan->pluck('pintu_air_id')->all() ?? [];
        $petak = $this->petakPilihan($u, $subs, $lamaPetak);
        $pintu = $this->pintuPilihan($u, $subs, $lamaPintu);

        // laporan sebelumnya milik pelapor ini per Sub-D.I. → isian awal petak (jenis, fase, umur bertambah, luas tanam)
        $pemilik = $laporan->user_id ?? $u->id;
        $sebelumnya = [];
        if (! $laporan->exists) {
            LaporanHarian::with('petak')->where('user_id', $pemilik)->whereIn('sub_daerah_irigasi_id', $subs->pluck('id'))
                ->orderBy('tanggal')->orderBy('id')->get()->groupBy('sub_daerah_irigasi_id')
                ->each(function ($g, $subId) use (&$sebelumnya) {
                    $l = $g->last();
                    $selang = (int) $l->tanggal->diffInDays(today());
                    $sebelumnya[$subId] = [
                        'tanggal' => $l->tanggal->translatedFormat('d M Y'),
                        'petak' => $l->petak->mapWithKeys(fn ($b) => [(int) $b->pintu_air_id => [
                            'luas_tanam_ha' => $b->luas_tanam_ha, 'jenis_tanaman' => $b->jenis_tanaman, 'fase' => $b->fase,
                            'umur_hst' => $b->umur_hst !== null ? $b->umur_hst + $selang : null,
                        ]])->all(),
                        'tindakan_operasi' => $l->tindakan_operasi,
                    ];
                });
        }

        return [
            'laporan' => $laporan, 'subs' => $subs, 'petak' => $petak, 'pintu' => $pintu, 'sebelumnya' => $sebelumnya,
            'isiPetak' => $laporan->petak->mapWithKeys(fn ($b) => [(int) $b->pintu_air_id => $b->only(['luas_tanam_ha', 'jenis_tanaman', 'fase', 'umur_hst', 'kecukupan_air', 'luas_kering_ha', 'keterangan'])])->all(),
            'isiBaca' => $laporan->sesiPintu?->pembacaan->mapWithKeys(fn ($b) => [$b->pintu_air_id => $b->only(['tma_cm', 'bukaan_cm', 'debit_lps', 'kondisi_pintu', 'kondisi_saluran', 'catatan'])])->all() ?? [],
        ];
    }

    /** @return array{0: array, 1: array<int, array>, 2: array<int, array>} [data laporan, baris petak (key pintu id / 0), pembacaan pintu] */
    private function validasi(Request $request, User $u, ?LaporanHarian $laporan = null): array
    {
        $batasMundur = $u->boleh('kelola-laporan') ? '2000-01-01' : today()->subDays(7)->toDateString();
        $maksFoto = 5 - ($laporan?->foto()->count() ?? 0);
        $pemilik = $laporan?->user_id ?? $u->id;
        $tgl = rescue(fn () => Carbon::parse($request->input('tanggal'))->toDateString(), (string) $request->input('tanggal'), false);
        $kunci = fn (array $a) => implode(',', array_keys($a));

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$batasMundur],
            'jam' => ['nullable', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(KodeIsian::JENIS_LAPOR))],
            'alasan' => ['nullable', 'required_if:jenis,insidental', Rule::in(array_keys(KodeIsian::ALASAN_PPA))],
            'alasan_lain' => ['nullable', 'required_if:alasan,lainnya', 'string', 'max:150'],
            'sub_daerah_irigasi_id' => [
                'required', 'exists:sub_daerah_irigasi,id',
                Rule::unique('laporan_harian')->where(fn ($q) => $q->where('tanggal', $tgl)->where('user_id', $pemilik))->ignore($laporan?->id),
            ],
            'mt' => ['nullable', Rule::in(array_keys(KodeIsian::MT))],
            'cuaca' => ['nullable', Rule::in(array_keys(KodeIsian::CUACA))],
            'hujan_7hari' => ['nullable', Rule::in(array_keys(KodeIsian::HUJAN_7_HARI))],
            'tindakan_operasi' => ['nullable', Rule::in(array_keys(KodeIsian::TINDAKAN_PINTU))],
            'atas_perintah' => ['nullable', 'string', 'max:150'],
            'baca' => ['nullable', 'array'],
            'baca.*.tma' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'baca.*.bukaan' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'baca.*.debit' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'baca.*.kondisi_pintu' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_PINTU))],
            'baca.*.kondisi_saluran' => ['nullable', Rule::in(array_keys(KodeIsian::KONDISI_SALURAN))],
            'baca.*.catatan' => ['nullable', 'string', 'max:255'],
            'petak' => ['nullable', 'array'],
            'petak.*.luas_tanam_ha' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'petak.*.jenis_tanaman' => ['nullable', Rule::in(array_keys(KodeIsian::JENIS_TANAMAN))],
            'petak.*.fase' => ['nullable', Rule::in(array_keys(KodeIsian::FASE))],
            'petak.*.umur_hst' => ['nullable', 'integer', 'min:0', 'max:365'],
            'petak.*.kecukupan_air' => ['nullable', Rule::in(array_keys(KodeIsian::KECUKUPAN_AIR))],
            'petak.*.luas_kering_ha' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'petak.*.keterangan' => ['nullable', 'string', 'max:255'],
            'kendala' => ['nullable', 'string', 'max:2000'],
            'usulan' => ['nullable', 'string', 'max:2000'],
            'dokumentasi' => ['nullable', 'array'],
            'dokumentasi.*' => [Rule::in(array_keys(KodeIsian::DOKUMENTASI_PPA))],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'foto' => ['nullable', 'array', 'max:'.max(0, $maksFoto)],
            'foto.*' => ['image', 'max:8192'],
        ], [
            'sub_daerah_irigasi_id.unique' => 'Anda sudah mengirim laporan untuk Sub-D.I. ini pada tanggal tersebut. Buka laporan itu untuk mengubahnya.',
            'alasan.required_if' => 'Pilih alasan laporan insidental.',
            'alasan_lain.required_if' => 'Tulis alasan lainnya.',
            'tanggal.after_or_equal' => 'Laporan hanya bisa diisi untuk 7 hari terakhir.',
            'baca.*.tma.numeric' => 'TMA harus angka (cm, dari papan duga).',
            'baca.*.debit.numeric' => 'Debit harus angka (L/dt).',
            'lat.between' => 'Lintang harus antara -12 dan -7 (wilayah NTT). Periksa koordinat.',
            'lng.between' => 'Bujur harus antara 118 dan 126 (wilayah NTT). Periksa koordinat.',
            'lat.required_with' => 'Isi lintang dan bujur sekaligus.',
            'lng.required_with' => 'Isi lintang dan bujur sekaligus.',
            'foto.max' => 'Maksimal 5 foto per laporan.',
        ], ['sub_daerah_irigasi_id' => 'Sub-D.I.', 'mt' => 'musim tanam']);

        $subId = (int) $data['sub_daerah_irigasi_id'];
        $sub = SubDaerahIrigasi::findOrFail($subId);
        $data['mt'] = $data['mt'] ?? $this->mt($data);
        $data['alasan'] = $data['jenis'] === 'insidental' ? ($data['alasan'] ?? null) : null;
        $data['alasan_lain'] = $data['alasan'] === 'lainnya' ? ($data['alasan_lain'] ?? null) : null;
        $data['dokumentasi'] = array_values($data['dokumentasi'] ?? []);

        $petak = $this->validasiPetak($request, $u, $sub, $laporan);
        $baca = $this->validasiPintu($request, $u, $subId, $laporan);
        unset($data['baca'], $data['petak'], $data['foto']);

        return [$data, $petak, $baca];
    }

    /**
     * Bagian C: kondisi tanaman per petak tersier. Petak tugas sendiri wajib diisi; bila belum ada penugasan,
     * minimal satu petak. Sub-D.I. tanpa petak tersier → satu baris "seluruh Sub-D.I." (kunci 0).
     *
     * @return array<int, array>
     */
    private function validasiPetak(Request $request, User $u, SubDaerahIrigasi $sub, ?LaporanHarian $laporan): array
    {
        $lama = $laporan?->petak()->pluck('pintu_air_id')->filter()->all() ?? [];
        $pilihan = $this->petakPilihan($u, collect([$sub]), $lama)[$sub->id];
        $daftar = collect($pilihan['daftar'])->keyBy('id');
        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $hasil = [];
        $galat = [];
        foreach ((array) $request->input('petak', []) as $id => $r) {
            $id = (int) $id;
            if (! $daftar->has($id)) {
                continue; // petak Sub-D.I. lain / bukan tugasnya
            }
            $jenis = $r['jenis_tanaman'] ?? null;
            if (! $jenis) {
                continue; // tidak diisi
            }
            $p = $daftar[$id];
            $bera = $jenis === 'B';
            $tanam = $bera ? 0.0 : ($angka($r['luas_tanam_ha'] ?? null) ?? 0.0);
            $kering = $bera ? 0.0 : ($angka($r['luas_kering_ha'] ?? null) ?? 0.0);
            $fase = ($r['fase'] ?? '') === '' ? null : (int) $r['fase'];
            $kecukupan = $r['kecukupan_air'] ?? null;
            if (! $bera) {
                if ($tanam <= 0) {
                    $galat["petak.$id.luas_tanam_ha"] = $p['label'].': isi luas tanam (Ha), atau pilih Bera bila tidak ditanam.';
                }
                if ($fase === null) {
                    $galat["petak.$id.fase"] = $p['label'].': pilih fase tanaman.';
                }
                if (! $kecukupan) {
                    $galat["petak.$id.kecukupan_air"] = $p['label'].': pilih kecukupan air.';
                }
                if ($kering > $tanam) {
                    $galat["petak.$id.luas_kering_ha"] = $p['label'].': luas terdampak kering tidak boleh melebihi luas tanam.';
                }
                if ($p['luas'] && $tanam > $p['luas'] * 1.05) {
                    $galat["petak.$id.luas_tanam_ha"] = $p['label'].': luas tanam melebihi luas baku petak ('.angka_r($p['luas']).' Ha). Periksa kembali, atau minta Manager memperbarui luas layanan.';
                }
            }
            $hasil[$id] = [
                'pintu_air_id' => $id ?: null, 'luas_baku_ha' => $p['luas'], 'luas_tanam_ha' => $tanam, 'jenis_tanaman' => $jenis,
                'fase' => $bera ? null : $fase, 'umur_hst' => $bera || ($r['umur_hst'] ?? '') === '' ? null : (int) $r['umur_hst'],
                'kecukupan_air' => $bera ? ($kecukupan ?: null) : $kecukupan, 'luas_kering_ha' => $kering,
                'keterangan' => ($r['keterangan'] ?? null) ?: null,
            ];
        }
        // petak tugas utama wajib diisi (pengganti tidak wajib); petak milik pelapor lain tidak diwajibkan saat diubah pengelola
        $wajib = ($laporan && $laporan->user_id !== $u->id) ? collect()
            : $daftar->filter(fn ($p) => $p['wajib'] && $p['aktif'])->keys()->reject(fn ($id) => isset($hasil[$id]));
        foreach ($wajib as $id) {
            $galat["petak.$id.jenis_tanaman"] = $daftar[$id]['label'].' adalah petak tugas Anda: isi kondisi tanamannya.';
        }
        if (! $hasil && ! $galat) {
            $galat['petak'] = $daftar->isEmpty()
                ? 'Anda belum ditugaskan ke petak tersier di Sub-D.I. ini. Minta Manager mengatur penugasan pintu air.'
                : 'Isi kondisi tanaman minimal satu petak tersier.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        return $hasil;
    }

    /**
     * Pintu air yang bisa diisi di laporan PPA, per Sub-D.I.: pintu dalam Sub-D.I. yang boleh diisi user
     * (tugasnya, atau belum ditugaskan; pengelola laporan: semua). Tugas sendiri ditampilkan lebih dulu.
     */
    private function pintuPilihan(User $u, $subs, array $tambahanId = []): array
    {
        $kelola = $u->boleh('kelola-laporan');
        if (! $kelola && ! $u->boleh('isi-pintu')) {
            return [];
        }
        $daftar = ($kelola ? PintuAir::query() : app(PintuService::class)->bolehIsi($u))
            ->whereIn('sub_daerah_irigasi_id', collect($subs)->pluck('id'))
            // intake & primer dibaca Manager Air; PPA membaca pintu sekunder/tersier
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('aktif', true)->whereIn('tingkat', ['sekunder', 'tersier']))->orWhereIn('id', $tambahanId))
            ->with('petugas:id', 'pembacaanTerakhir')->urut()->get();

        return $daftar->groupBy('sub_daerah_irigasi_id')->map(fn ($g) => $g->map(fn (PintuAir $p) => [
            'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'tingkat' => $p->tingkat, 'tingkat_label' => $p->tingkat_label,
            'dmin' => $p->debit_min_lps, 'dmaks' => $p->debit_maks_lps, 'tmin' => $p->tma_min_cm, 'tmaks' => $p->tma_maks_cm,
            'rencana' => $p->debit_rencana_lps, 'saya' => $p->petugas->contains('id', $u->id), 'aktif' => (bool) $p->aktif,
            'wajib' => $p->petugas->contains(fn ($x) => $x->id === $u->id && $x->pivot->peran === 'utama'),
            'terakhir' => $p->pembacaanTerakhir ? $p->pembacaanTerakhir->tanggal->translatedFormat('d M').': TMA '.angka_r($p->pembacaanTerakhir->tma_cm).' cm'.($p->pembacaanTerakhir->debit_lps !== null ? ' · '.angka_r($p->pembacaanTerakhir->debit_lps).' L/dt' : '') : null,
        ])->sortByDesc('saya')->values()->all())->all();
    }

    /**
     * Bagian B: pembacaan pintu. Pintu tugas sendiri wajib diisi TMA-nya (debit boleh kosong bila tidak ada tabel debit).
     *
     * @return array<int, array>
     */
    private function validasiPintu(Request $request, User $u, int $subId, ?LaporanHarian $laporan): array
    {
        $lama = $laporan?->sesiPintu?->pembacaan()->pluck('pintu_air_id')->all() ?? [];
        $daftar = collect($this->pintuPilihan($u, SubDaerahIrigasi::whereKey($subId)->get(), $lama)[$subId] ?? [])->keyBy('id');
        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $hasil = [];
        foreach ((array) $request->input('baca', []) as $id => $r) {
            $id = (int) $id;
            if (! $daftar->has($id)) {
                continue; // pintu Sub-D.I. lain (mis. setelah ganti pilihan) diabaikan
            }
            $b = [
                'tma_cm' => $angka($r['tma'] ?? null), 'bukaan_cm' => $angka($r['bukaan'] ?? null), 'debit_lps' => $angka($r['debit'] ?? null),
                'kondisi_pintu' => ($r['kondisi_pintu'] ?? null) ?: null, 'kondisi_saluran' => ($r['kondisi_saluran'] ?? null) ?: null,
                'catatan' => ($r['catatan'] ?? null) ?: null,
            ];
            if (collect($b)->filter(fn ($v) => $v !== null)->isEmpty()) {
                continue;
            }
            $hasil[$id] = $b;
        }
        // pemilik laporan (PPA) wajib membaca pintu tugasnya; laporan lama tanpa pembacaan boleh diubah tanpa mengisinya
        if (! $laporan || $lama) {
            $tugas = $laporan && $laporan->user_id !== $u->id ? collect() : $daftar->filter(fn ($p) => $p['wajib'] && $p['aktif']);
            $galat = [];
            foreach ($tugas as $id => $p) {
                if (($hasil[$id]['tma_cm'] ?? null) === null) {
                    $galat["baca.$id.tma"] = 'Pintu '.$p['kode'].' tugas Anda: isi TMA hulu (cm, dari papan duga).';
                }
            }
            if ($galat) {
                throw ValidationException::withMessages($galat);
            }
        }

        return $hasil;
    }

    /** Simpan baris petak (Bagian C) lalu hitung ulang total luas & status laporan. */
    private function simpanPetak(LaporanHarian $l, array $petak): void
    {
        $l->petak()->get()->each(function ($b) use ($petak) {
            if (! isset($petak[(int) $b->pintu_air_id])) {
                $b->delete();
            }
        });
        foreach ($petak as $id => $r) {
            LaporanPetak::updateOrCreate(['laporan_harian_id' => $l->id, 'pintu_air_id' => $id ?: null], $r);
        }
        $l->unsetRelation('petak');
        $l->hitungTotal();
    }

    /** Simpan pembacaan pintu (Bagian B) sebagai sesi pembacaan pintu jenis "harian" yang terhubung ke laporan ini. */
    private function simpanPintu(LaporanHarian $l, array $baca): void
    {
        $sesi = $l->sesiPintu()->first();
        if (! $baca) {
            $sesi?->delete();

            return;
        }
        $diId = SubDaerahIrigasi::whereKey($l->sub_daerah_irigasi_id)->value('daerah_irigasi_id');
        $sesi ??= new SesiPintu(['laporan_harian_id' => $l->id, 'user_id' => $l->user_id, 'jenis' => 'harian']);
        $sesi->fill(['daerah_irigasi_id' => $diId, 'tanggal' => $l->tanggal, 'jam' => $l->jam ?? now()->format('H:i'), 'lat' => $l->lat, 'lng' => $l->lng])->save();
        $sesi->pembacaan()->delete();
        foreach ($baca as $id => $r) {
            $sesi->pembacaan()->create(['pintu_air_id' => $id, 'tanggal' => $l->tanggal] + $r);
        }
    }

    /**
     * Petak tersier per Sub-D.I. (pintu tersier aktif): tugas user atau belum ditugaskan; pengelola: semua.
     * Sub-D.I. tanpa petak tersier sama sekali → satu baris "seluruh Sub-D.I." (id 0).
     *
     * @return array<int, array{tanpa_petak: bool, daftar: array}>
     */
    private function petakPilihan(User $u, $subs, array $tambahanId = []): array
    {
        $subIds = collect($subs)->pluck('id')->all();
        $semua = PintuAir::tersier()->whereIn('sub_daerah_irigasi_id', $subIds)
            ->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', $tambahanId))
            ->with('petugas:id')->orderBy('urutan')->orderBy('kode')->get();
        $hasil = [];
        foreach ($subs as $sub) {
            $daftar = $semua->where('sub_daerah_irigasi_id', $sub->id);
            $kelola = $u->boleh('kelola-laporan') || $u->bolehKelolaPintu((int) $sub->daerah_irigasi_id);
            $boleh = $kelola ? $daftar : $daftar->filter(fn ($p) => $p->petugas->isEmpty() || $p->petugas->contains('id', $u->id) || in_array($p->id, $tambahanId, true));
            $baris = $boleh->map(fn ($p) => [
                'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'luas' => $p->luas_layanan_ha, 'aktif' => (bool) $p->aktif,
                'label' => 'Petak '.$p->kode, 'lat' => $p->lat, 'lng' => $p->lng, 'saya' => $p->petugas->contains('id', $u->id),
                'wajib' => $p->petugas->contains(fn ($x) => $x->id === $u->id && $x->pivot->peran === 'utama'),
            ])->sortByDesc('saya')->values();
            if ($daftar->isEmpty()) {
                $baris = collect([['id' => 0, 'kode' => null, 'nama' => 'Seluruh Sub-D.I.', 'luas' => $sub->luas_ha ? (float) $sub->luas_ha : null,
                    'aktif' => true, 'label' => 'Seluruh Sub-D.I. '.$sub->nama, 'lat' => null, 'lng' => null, 'saya' => false, 'wajib' => false]]);
            }
            $hasil[$sub->id] = ['tanpa_petak' => $daftar->isEmpty(), 'daftar' => $baris->all()];
        }

        return $hasil;
    }

    private function mt(array $data): ?int
    {
        $di = SubDaerahIrigasi::find($data['sub_daerah_irigasi_id'])?->daerahIrigasi;

        return $di?->mtBerjalan(Carbon::parse($data['tanggal']))?->mt;
    }
}
