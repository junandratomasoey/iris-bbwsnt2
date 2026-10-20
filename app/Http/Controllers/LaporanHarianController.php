<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\LaporanHarian;
use App\Models\PintuAir;
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
            ->with(['subDaerahIrigasi.daerahIrigasi:id,nama', 'user:id,name,no_hp', 'foto', 'petak:id,kode,nama'])
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
            ? LaporanHarian::where('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id)->when($request->integer('petak'), fn ($q, $p) => $q->where('pintu_air_id', $p))->latest('tanggal')->first()
            : null;
        if ($sebelumnya) {
            $laporan->fill($sebelumnya->only(['fase_tanaman', 'luas_tanam_ha',
                'luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'jenis_palawija', 'jenis_horti', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha', 'luas_puso_ha']));
        }

        $laporan->pintu_air_id = $request->integer('petak') ?: null;
        $petak = $this->petakPilihan($request->user(), $subs);
        $pintu = $this->pintuPilihan($request->user(), $subs);
        $bacaAwal = [];

        return view('laporan.form', compact('laporan', 'subs', 'sebelumnya', 'petak', 'pintu', 'bacaAwal'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', LaporanHarian::class);
        $data = $this->validasi($request);
        abort_unless($request->user()->bolehLapor((int) $data['sub_daerah_irigasi_id']), 403, 'Sub-D.I. ini bukan wilayah tugas Anda.');

        $data['user_id'] = $request->user()->id;
        $data['mt'] = $this->mt($data);
        $baca = $data['baca'];
        unset($data['baca']);
        $laporan = LaporanHarian::create($data);
        $this->simpanPintu($laporan, $baca);
        $this->foto->simpan($laporan, $request->file('foto', []), 'laporan', $laporan->lat, $laporan->lng);

        return redirect()->route('laporan.show', $laporan)->with('ok', 'Laporan '.$laporan->tanggal->translatedFormat('d M Y').' terkirim.')->with('hapus_draft', true);
    }

    public function show(LaporanHarian $laporan)
    {
        $this->authorize('view', $laporan);
        $laporan->load('subDaerahIrigasi.daerahIrigasi.kabupaten', 'user', 'verifikator', 'foto', 'petak', 'sesiPintu.pembacaan.pintu');
        $riwayat = LaporanHarian::where('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id)->where('petak_kunci', $laporan->petak_kunci)
            ->where('tanggal', '<=', $laporan->tanggal)->latest('tanggal')->limit(14)->get()->reverse()->values();

        return view('laporan.show', compact('laporan', 'riwayat'));
    }

    public function edit(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $laporan->load('foto');
        $subs = $this->subPelaporan($request, true)->push($laporan->subDaerahIrigasi)->unique('id');
        $sebelumnya = null;
        $petak = $this->petakPilihan($request->user(), $subs, $laporan);
        $laporan->load('sesiPintu.pembacaan');
        $bacaAwal = $laporan->sesiPintu?->pembacaan->mapWithKeys(fn ($b) => [$b->pintu_air_id => ['debit' => $b->debit_lps, 'tma' => $b->tma_cm]])->all() ?? [];
        $pintu = $this->pintuPilihan($request->user(), $subs, array_keys($bacaAwal));

        return view('laporan.form', compact('laporan', 'subs', 'sebelumnya', 'petak', 'pintu', 'bacaAwal'));
    }

    public function update(Request $request, LaporanHarian $laporan)
    {
        $this->authorize('update', $laporan);
        $data = $this->validasi($request, $laporan);
        if ((int) $data['sub_daerah_irigasi_id'] !== $laporan->sub_daerah_irigasi_id) {
            $u = $request->user();
            abort_unless($u->bolehLapor((int) $data['sub_daerah_irigasi_id'])
                || ($u->boleh('kelola-laporan') && $u->bolehLihatSub((int) $data['sub_daerah_irigasi_id'])), 403);
        }
        $data['mt'] = $this->mt($data);
        if ($laporan->status_verifikasi === 'perbaikan' && $laporan->user_id === $request->user()->id) {
            $data['status_verifikasi'] = 'menunggu';
        }
        $baca = $data['baca'];
        unset($data['baca']);
        $laporan->update($data);
        $this->simpanPintu($laporan, $baca);
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
        $nama = 'laporan-harian-'.now()->format('Ymd-His').'.csv';
        $q = $this->query($request)->with(['subDaerahIrigasi.daerahIrigasi.kabupaten', 'user:id,name', 'petak:id,kode'])->orderBy('tanggal');

        return response()->streamDownload(function () use ($q) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Tanggal', 'Kabupaten', 'D.I.', 'Sub-D.I.', 'Petak tersier', 'MT', 'Debit intake (L/dt)', 'TMA (cm)', 'Kondisi saluran', 'Bukaan pintu (%)', 'Fase', 'Luas tanam (Ha)', 'Padi (Ha)', 'Palawija (Ha)', 'Jenis palawija', 'Hortikultura (Ha)', 'Jenis hortikultura', 'Luas hijau (Ha)', 'Luas kekeringan (Ha)', 'Luas panen (Ha)', 'Luas puso (Ha)', 'Status', 'Kendala', 'Pelapor', 'Verifikasi', 'Lat', 'Lng'], ';');
            $q->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $l) {
                    $s = $l->subDaerahIrigasi;
                    fputcsv($out, [
                        $l->tanggal->format('Y-m-d'), $s->daerahIrigasi->kabupaten?->nama, $s->daerahIrigasi->nama, $s->nama, $l->petak?->kode, $l->mt,
                        $l->debit_intake_lps, $l->tma_cm, $l->kondisi_saluran, $l->bukaan_pintu_persen, $l->fase_tanaman,
                        $l->luas_tanam_ha, $l->luas_padi_ha, $l->luas_palawija_ha, $l->jenis_palawija, $l->luas_horti_ha, $l->jenis_horti, $l->luas_hijau_ha, $l->luas_kering_ha, $l->luas_panen_ha, $l->luas_puso_ha,
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

    private function subPelaporan(Request $request, bool $ubah = false)
    {
        $u = $request->user();
        // pengelola laporan boleh memindahkan laporan ke Sub-D.I. mana pun dalam jangkauannya
        $ids = $ubah && $u->boleh('kelola-laporan') ? $u->subIdsTerjangkau() : $u->subIdsPelaporan();

        return SubDaerahIrigasi::with('daerahIrigasi:id,nama,lat,lng')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('daerah_irigasi_id')->orderBy('nama')->get();
    }

    private function validasi(Request $request, ?LaporanHarian $laporan = null): array
    {
        $pengelola = $request->user()->boleh('kelola-laporan');
        $batasMundur = $pengelola ? '2000-01-01' : today()->subDays(7)->toDateString();
        $maksFoto = 5 - ($laporan?->foto()->count() ?? 0);

        // Luas tanam = padi + palawija + hortikultura. Form lama (tanpa rincian) dianggap seluruhnya padi.
        $rincian = ['luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha'];
        if (collect($rincian)->contains(fn ($k) => $request->has($k))) {
            $angka = fn ($k) => is_numeric($request->input($k)) ? (float) $request->input($k) : 0.0;
            $request->merge(['luas_tanam_ha' => round(collect($rincian)->sum($angka), 2)]
                + collect($rincian)->mapWithKeys(fn ($k) => [$k => $request->filled($k) ? $request->input($k) : 0])->all());
        } else {
            $request->merge(['luas_padi_ha' => $request->input('luas_tanam_ha'), 'luas_palawija_ha' => 0, 'luas_horti_ha' => 0]);
        }
        foreach (['palawija', 'horti'] as $k) { // jenis hanya disimpan bila luasnya diisi
            if (! ((float) $request->input("luas_{$k}_ha") > 0)) {
                $request->merge(["jenis_$k" => null]);
            }
        }

        // petak tersier: wajib bagi PPA bila Sub-D.I. sudah punya petak tersier
        $subId = $request->integer('sub_daerah_irigasi_id');
        $pilihan = $subId ? ($this->petakPilihan($request->user(), SubDaerahIrigasi::whereKey($subId)->get(), $laporan)[$subId] ?? null) : null;
        $wajibPetak = (bool) ($pilihan['wajib'] ?? false);
        $idPetak = collect($pilihan['daftar'] ?? [])->pluck('id')->all();
        $petakKunci = (int) $request->input('pintu_air_id') ?: 0;

        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$batasMundur],
            'sub_daerah_irigasi_id' => [
                'required', 'exists:sub_daerah_irigasi,id',
                Rule::unique('laporan_harian')->where(fn ($q) => $q->where('tanggal', rescue(fn () => Carbon::parse($request->input('tanggal'))->toDateString(), (string) $request->input('tanggal'), false))
                    ->where('petak_kunci', $petakKunci))->ignore($laporan?->id),
            ],
            'pintu_air_id' => [Rule::requiredIf($wajibPetak), 'nullable', 'integer', Rule::in($idPetak)],
            'baca' => ['nullable', 'array'],
            'baca.*.debit' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'baca.*.tma' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'fase_tanaman' => ['nullable', Rule::in(array_keys(LaporanHarian::FASE))],
            'luas_tanam_ha' => ['required', 'numeric', 'min:0', 'max:20000'],
            'luas_padi_ha' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'luas_palawija_ha' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'luas_horti_ha' => ['nullable', 'numeric', 'min:0', 'max:20000'],
            'jenis_palawija' => ['nullable', 'string', 'max:150'],
            'jenis_horti' => ['nullable', 'string', 'max:150'],
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
            'sub_daerah_irigasi_id.unique' => $petakKunci ? 'Petak ini sudah punya laporan pada tanggal tersebut. Buka laporan itu untuk mengubahnya.' : 'Sub-D.I. ini sudah punya laporan pada tanggal tersebut. Buka laporan itu untuk mengubahnya.',
            'pintu_air_id.required' => $idPetak ? 'Pilih petak tersier yang Anda laporkan.' : 'Anda belum ditugaskan ke petak tersier di Sub-D.I. ini. Minta Manager mengatur penugasan pintu air.',
            'pintu_air_id.in' => 'Petak tersier ini bukan tugas Anda atau bukan bagian Sub-D.I. yang dipilih.',
            'baca.*.debit.numeric' => 'Debit pintu harus angka.',
            'baca.*.tma.numeric' => 'TMA pintu harus angka (cm, dari mistar).',
            'tanggal.after_or_equal' => 'Laporan hanya bisa diisi untuk 7 hari terakhir.',
            'lte' => ':attribute tidak boleh lebih besar dari luas tanam.',
            'lat.between' => 'Lintang harus antara -12 dan -7 (wilayah NTT). Periksa koordinat.',
            'lng.between' => 'Bujur harus antara 118 dan 126 (wilayah NTT). Periksa koordinat.',
            'lat.required_with' => 'Isi lintang dan bujur sekaligus.',
            'lng.required_with' => 'Isi lintang dan bujur sekaligus.',
            'foto.max' => 'Maksimal 5 foto per laporan.',
        ], [
            'sub_daerah_irigasi_id' => 'Sub-D.I.', 'debit_intake_lps' => 'debit intake', 'tma_cm' => 'TMA',
            'luas_tanam_ha' => 'luas tanam', 'luas_padi_ha' => 'luas padi', 'luas_palawija_ha' => 'luas palawija', 'luas_horti_ha' => 'luas hortikultura',
            'jenis_palawija' => 'jenis palawija', 'jenis_horti' => 'jenis hortikultura', 'luas_hijau_ha' => 'luas hijau', 'luas_kering_ha' => 'luas kekeringan',
            'luas_panen_ha' => 'luas panen', 'luas_puso_ha' => 'luas puso', 'kondisi_saluran' => 'kondisi saluran',
        ]);

        $data['pintu_air_id'] = $data['pintu_air_id'] ?? null;
        $data['baca'] = $this->validasiPintu($request, (int) $data['sub_daerah_irigasi_id'], $data['pintu_air_id'], $laporan);
        $luasPetak = $data['pintu_air_id'] ? collect($pilihan['daftar'])->firstWhere('id', (int) $data['pintu_air_id'])['luas'] ?? null : null;
        if ($luasPetak && (float) $data['luas_tanam_ha'] > $luasPetak * 1.05) {
            throw \Illuminate\Validation\ValidationException::withMessages(['luas_padi_ha' => 'Luas tanam melebihi luas layanan petak ('.angka_r($luasPetak).' Ha). Periksa kembali, atau minta Manager memperbarui luas layanan petak.']);
        }

        return $data;
    }

    /**
     * Pintu air yang bisa diisi debit & TMA-nya di laporan harian, per Sub-D.I.:
     * pintu dalam Sub-D.I. yang boleh diisi user (tugasnya, atau belum ditugaskan). Tugas sendiri ditampilkan lebih dulu.
     */
    private function pintuPilihan(\App\Models\User $u, $subs, array $tambahanId = []): array
    {
        $kelola = $u->boleh('kelola-laporan');
        if (! $kelola && ! $u->boleh('isi-pintu')) {
            return [];
        }
        $daftar = ($kelola ? PintuAir::query() : app(\App\Services\PintuService::class)->bolehIsi($u))
            ->whereIn('sub_daerah_irigasi_id', collect($subs)->pluck('id'))
            ->where(fn ($q) => $q->where('aktif', true)->orWhereIn('id', $tambahanId))
            ->with('petugas:id', 'pembacaanTerakhir')->urut()->get();

        return $daftar->groupBy('sub_daerah_irigasi_id')->map(fn ($g) => $g->map(fn (PintuAir $p) => [
            'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'tingkat' => $p->tingkat, 'tingkat_label' => $p->tingkat_label,
            'dmin' => $p->debit_min_lps, 'dmaks' => $p->debit_maks_lps, 'tmin' => $p->tma_min_cm, 'tmaks' => $p->tma_maks_cm,
            'rencana' => $p->debit_rencana_lps, 'saya' => $p->petugas->contains('id', $u->id),
            'terakhir' => $p->pembacaanTerakhir ? $p->pembacaanTerakhir->tanggal->translatedFormat('d M').': '.angka_r($p->pembacaanTerakhir->debit_lps).' L/dt · '.angka_r($p->pembacaanTerakhir->tma_cm).' cm' : null,
        ])->values()->all())->all();
    }

    /**
     * Debit & TMA pintu yang diisi di laporan: hanya pintu yang boleh diisi user di Sub-D.I. ini.
     * Pintu petak yang dilaporkan wajib diisi debit dan TMA-nya.
     *
     * @return array<int, array{debit: ?float, tma: ?float}>
     */
    private function validasiPintu(Request $request, int $subId, ?int $petakId, ?LaporanHarian $laporan): array
    {
        $u = $request->user();
        $lama = $laporan?->sesiPintu?->pembacaan()->pluck('pintu_air_id')->all() ?? [];
        $boleh = collect($this->pintuPilihan($u, SubDaerahIrigasi::whereKey($subId)->get(), $lama)[$subId] ?? [])->pluck('id')->all();
        $angka = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $hasil = [];
        foreach ((array) $request->input('baca', []) as $id => $r) {
            $debit = $angka($r['debit'] ?? null);
            $tma = $angka($r['tma'] ?? null);
            if ($debit === null && $tma === null) {
                continue;
            }
            if (! in_array((int) $id, $boleh, true)) {
                continue; // pintu Sub-D.I. lain (mis. setelah ganti pilihan) diabaikan
            }
            $hasil[(int) $id] = ['debit' => $debit, 'tma' => $tma];
        }
        // wajib untuk laporan baru; laporan lama (sebelum ada isian pintu) boleh diubah tanpa mengisinya
        if ($petakId && in_array($petakId, $boleh, true) && (! $laporan || $lama)) {
            $p = $hasil[$petakId] ?? ['debit' => null, 'tma' => null];
            $galat = [];
            if ($p['debit'] === null) {
                $galat["baca.$petakId.debit"] = 'Isi debit pintu petak ini (L/dt).';
            }
            if ($p['tma'] === null) {
                $galat["baca.$petakId.tma"] = 'Isi TMA pintu petak ini (cm, baca di mistar).';
            }
            if ($galat) {
                throw \Illuminate\Validation\ValidationException::withMessages($galat);
            }
        }

        return $hasil;
    }

    /** Simpan debit & TMA pintu sebagai pembacaan pintu (jenis "harian") yang terhubung ke laporan ini. */
    private function simpanPintu(LaporanHarian $l, array $baca): void
    {
        $sesi = $l->sesiPintu()->first();
        if (! $baca) {
            $sesi?->delete();

            return;
        }
        $diId = SubDaerahIrigasi::whereKey($l->sub_daerah_irigasi_id)->value('daerah_irigasi_id');
        $sesi ??= new \App\Models\SesiPintu(['laporan_harian_id' => $l->id, 'user_id' => $l->user_id, 'jenis' => 'harian', 'jam' => now()->format('H:i')]);
        $sesi->fill(['daerah_irigasi_id' => $diId, 'tanggal' => $l->tanggal, 'lat' => $l->lat, 'lng' => $l->lng])->save();
        $sesi->pembacaan()->delete();
        foreach ($baca as $id => $r) {
            $sesi->pembacaan()->create(['pintu_air_id' => $id, 'tanggal' => $l->tanggal, 'debit_lps' => $r['debit'], 'tma_cm' => $r['tma']]);
        }
    }

    /**
     * Petak tersier yang bisa dipilih per Sub-D.I.: pintu tersier aktif yang ditugaskan kepada user
     * atau belum ditugaskan ke siapa pun (pengelola: semua). Wajib dipilih oleh PPA bila Sub-D.I. punya petak.
     *
     * @return array<int, array{wajib: bool, daftar: array}>
     */
    private function petakPilihan(\App\Models\User $u, $subs, ?LaporanHarian $laporan = null): array
    {
        $subIds = collect($subs)->pluck('id')->all();
        $semua = PintuAir::tersier()->whereIn('sub_daerah_irigasi_id', $subIds)
            ->where(fn ($q) => $q->where('aktif', true)->when($laporan?->pintu_air_id, fn ($w) => $w->orWhere('id', $laporan->pintu_air_id)))
            ->with('petugas:id', 'pembacaanTerakhir')->orderBy('urutan')->orderBy('kode')->get();
        $hasil = [];
        foreach ($subs as $sub) {
            $daftar = $semua->where('sub_daerah_irigasi_id', $sub->id);
            $kelola = $u->boleh('kelola-laporan') || $u->bolehKelolaPintu((int) $sub->daerah_irigasi_id);
            $boleh = $kelola ? $daftar : $daftar->filter(fn ($p) => $p->petugas->isEmpty() || $p->petugas->contains('id', $u->id) || $p->id === $laporan?->pintu_air_id);
            $hasil[$sub->id] = [
                'wajib' => $daftar->isNotEmpty() && $u->hasRole(Role::PETUGAS),
                'daftar' => $boleh->map(fn ($p) => [
                    'id' => $p->id, 'kode' => $p->kode, 'nama' => $p->nama, 'luas' => $p->luas_layanan_ha,
                    'lat' => $p->lat, 'lng' => $p->lng, 'saya' => $p->petugas->contains('id', $u->id),
                    'bacaan' => $p->pembacaanTerakhir && $p->pembacaanTerakhir->tanggal->gte(today()->subDays(2))
                        ? ['debit' => $p->pembacaanTerakhir->debit_lps, 'tma' => $p->pembacaanTerakhir->tma_cm, 'tanggal' => $p->pembacaanTerakhir->tanggal->translatedFormat('d M')] : null,
                ])->sortByDesc('saya')->values()->all(),
            ];
        }

        return $hasil;
    }

    private function mt(array $data): ?int
    {
        $di = SubDaerahIrigasi::find($data['sub_daerah_irigasi_id'])?->daerahIrigasi;

        return $di?->mtBerjalan(Carbon::parse($data['tanggal']))?->mt;
    }
}
