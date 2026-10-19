<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\PintuAir;
use App\Models\SkemaJaringan;
use App\Models\SubDaerahIrigasi;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Daftar pintu air per D.I. (tambah banyak sekaligus, ubah, hapus) dan file skema jaringan. */
class PintuAirController extends Controller
{
    public function create(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('di'));
        $this->izinkan($request, $di->id);

        return view('pintu.master.tambah', [
            'di' => $di,
            'subList' => $di->subDaerahIrigasi()->get(['id', 'nama']),
            'kodeAda' => $di->pintuAir()->orderBy('kode')->pluck('kode'),
            'urutanBerikut' => (int) $di->pintuAir()->max('urutan') + 1,
        ]);
    }

    public function store(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('daerah_irigasi_id'));
        $this->izinkan($request, $di->id);

        // baris kosong (tanpa kode) diabaikan
        $baris = collect($request->input('pintu', []))->filter(fn ($r) => filled($r['kode'] ?? null))->values()->all();
        $request->merge(['pintu' => $baris]);
        $aturan = ['pintu' => ['required', 'array', 'min:1', 'max:100']];
        foreach ($this->aturanPintu($di) as $k => $v) {
            $aturan['pintu.*.'.$k] = $v;
        }
        $aturan['pintu.*.kode'][] = 'distinct:ignore_case';
        $data = $request->validate($aturan, $this->pesan() + [
            'pintu.required' => 'Isi minimal satu pintu (kolom kode wajib).',
            'pintu.*.kode.distinct' => 'Kode pintu ganda di isian.',
            'pintu.*.kode.unique' => 'Kode :input sudah dipakai di D.I. ini.',
        ]);

        $urutan = (int) $di->pintuAir()->max('urutan');
        foreach ($data['pintu'] as $r) {
            PintuAir::create($this->rapikan($r) + ['daerah_irigasi_id' => $di->id, 'urutan' => ++$urutan]);
        }

        return redirect()->route('pintu.index', ['di' => $di->id])->with('ok', count($data['pintu']).' pintu air ditambahkan ke D.I. '.$di->nama.'.');
    }

    public function edit(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        $pintu->load('daerahIrigasi:id,nama');

        return view('pintu.master.ubah', [
            'p' => $pintu,
            'subList' => SubDaerahIrigasi::where('daerah_irigasi_id', $pintu->daerah_irigasi_id)->orderBy('nama')->get(['id', 'nama']),
            'bangunanList' => Bangunan::where('daerah_irigasi_id', $pintu->daerah_irigasi_id)->orderBy('nama')->get(['id', 'nama', 'jenis']),
            'jumlahBacaan' => $pintu->pembacaan()->count(),
        ]);
    }

    public function update(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        $di = $pintu->daerahIrigasi;
        $data = $request->validate($this->aturanPintu($di, $pintu) + [
            'bangunan_id' => ['nullable', Rule::exists('bangunan', 'id')->where('daerah_irigasi_id', $di->id)],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['boolean'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-12,-7'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:118,126'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ], $this->pesan());
        $pintu->update($this->rapikan($data) + ['aktif' => $request->boolean('aktif'), 'urutan' => (int) ($data['urutan'] ?? $pintu->urutan)]);

        return redirect()->route('pintu.show', $pintu)->with('ok', "Pintu {$pintu->kode} diperbarui.");
    }

    public function destroy(Request $request, PintuAir $pintu)
    {
        $this->izinkan($request, $pintu->daerah_irigasi_id);
        if ($pintu->pembacaan()->exists()) {
            $pintu->update(['aktif' => false]);

            return redirect()->route('pintu.index', ['di' => $pintu->daerah_irigasi_id])
                ->with('ok', "Pintu {$pintu->kode} sudah punya data pembacaan, jadi dinonaktifkan (riwayatnya tetap tersimpan).");
        }
        $pintu->delete();

        return redirect()->route('pintu.index', ['di' => $pintu->daerah_irigasi_id])->with('ok', "Pintu {$pintu->kode} dihapus.");
    }

    // ---------- skema jaringan ----------

    public function skemaStore(Request $request, DaerahIrigasi $di)
    {
        $this->izinkan($request, $di->id);
        $sisa = SkemaJaringan::MAKS_FILE - $di->skemaJaringan()->count();
        $request->validate([
            'judul' => ['nullable', 'string', 'max:150'],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.SkemaJaringan::MAKS_KB],
        ], [
            'file.required' => 'Pilih file skema (JPG/PNG/PDF).',
            'file.mimes' => 'Skema harus berupa gambar (JPG/PNG/WEBP) atau PDF.',
            'file.max' => 'Ukuran file maksimal 20 MB.',
        ]);
        if ($sisa < 1) {
            return back()->withErrors(['file' => 'Sudah ada '.SkemaJaringan::MAKS_FILE.' file skema. Hapus yang lama dulu.']);
        }
        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $path = $file->storeAs('skema/'.$di->id, Str::uuid().'.'.$ext, 'uploads');
        $di->skemaJaringan()->create([
            'judul' => $request->input('judul') ?: Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 150, ''),
            'path' => $path,
            'mime' => $file->getMimeType() ?: 'application/octet-stream',
            'ukuran' => $file->getSize(),
            'user_id' => $request->user()->id,
        ]);

        return back()->with('ok', 'Skema jaringan diunggah.');
    }

    public function skemaDestroy(Request $request, SkemaJaringan $skema)
    {
        $this->izinkan($request, $skema->daerah_irigasi_id);
        $skema->delete();

        return back()->with('ok', 'File skema dihapus.');
    }

    // ---------- bantuan ----------

    private function izinkan(Request $request, int $diId): void
    {
        abort_unless($request->user()->bolehKelolaPintu($diId), 403, 'Anda tidak punya izin mengelola pintu air di D.I. ini.');
    }

    private function aturanPintu(DaerahIrigasi $di, ?PintuAir $p = null): array
    {
        $angka = fn ($maks) => ['nullable', 'numeric', 'min:0', 'max:'.$maks];

        return [
            'kode' => ['required', 'string', 'max:30', Rule::unique('pintu_air', 'kode')->where('daerah_irigasi_id', $di->id)->ignore($p?->id)],
            'nama' => ['nullable', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(array_keys(PintuAir::JENIS))],
            'sub_daerah_irigasi_id' => ['nullable', Rule::exists('sub_daerah_irigasi', 'id')->where('daerah_irigasi_id', $di->id)],
            'saluran' => ['nullable', 'string', 'max:255'],
            'luas_layanan_ha' => $angka(100000),
            'debit_min_lps' => $angka(1000000),
            'debit_maks_lps' => [...$angka(1000000), $this->tidakDiBawah('debit_min_lps')],
            'tma_min_cm' => $angka(10000),
            'tma_maks_cm' => [...$angka(10000), $this->tidakDiBawah('tma_min_cm')],
        ];
    }

    /** Batas atas tidak boleh di bawah batas bawah (bila keduanya diisi). Bekerja juga untuk isian banyak baris. */
    private function tidakDiBawah(string $kolomMin): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($kolomMin) {
            $min = data_get(request()->all(), preg_replace('/[^.]+$/', $kolomMin, $attribute));
            if (is_numeric($value) && is_numeric($min) && (float) $value < (float) $min) {
                $fail('Batas atas tidak boleh lebih kecil dari batas bawah.');
            }
        };
    }

    private function pesan(): array
    {
        return [
            'kode.unique' => 'Kode ini sudah dipakai di D.I. ini.',
            '*.sub_daerah_irigasi_id.exists' => 'Sub-D.I. tidak termasuk D.I. ini.',
            'sub_daerah_irigasi_id.exists' => 'Sub-D.I. tidak termasuk D.I. ini.',
        ];
    }

    private function rapikan(array $r): array
    {
        $r['kode'] = trim($r['kode']);
        foreach (['luas_layanan_ha', 'debit_min_lps', 'debit_maks_lps', 'tma_min_cm', 'tma_maks_cm'] as $k) {
            if (array_key_exists($k, $r) && $r[$k] === '') {
                $r[$k] = null;
            }
        }

        return collect($r)->only([
            'kode', 'nama', 'jenis', 'sub_daerah_irigasi_id', 'saluran', 'luas_layanan_ha', 'debit_min_lps', 'debit_maks_lps',
            'tma_min_cm', 'tma_maks_cm', 'bangunan_id', 'lat', 'lng', 'keterangan',
        ])->all();
    }
}
