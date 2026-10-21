<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\DaerahIrigasi;
use App\Services\ImporPintuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Impor data pintu air per D.I. dari template Excel: unduh template → isi → unggah → periksa → terapkan. */
class ImporPintuController extends Controller
{
    public function __construct(private ImporPintuService $svc) {}

    public function form(Request $request)
    {
        $di = DaerahIrigasi::findOrFail($request->integer('di'));
        $this->izinkan($request, $di);

        return view('pintu.master.impor', ['di' => $di, 'hasil' => null, 'token' => null, 'jumlahPintu' => $di->pintuAir()->count()]);
    }

    public function template(Request $request, DaerahIrigasi $di)
    {
        $this->izinkan($request, $di);
        $path = $this->svc->template($di);

        return response()->download($path, 'data-pintu-air-'.Str::slug($di->nama).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function periksa(Request $request, DaerahIrigasi $di)
    {
        $this->izinkan($request, $di);
        $request->validate(['file' => ['required', 'file', 'max:5120']], [
            'file.required' => 'Pilih file Excel (.xlsx) hasil isian template.', 'file.max' => 'Ukuran file maksimal 5 MB.',
        ]);
        if (strtolower($request->file('file')->getClientOriginalExtension()) !== 'xlsx') {
            return back()->withErrors(['file' => 'File harus berformat .xlsx (Excel). Bila memakai Excel lama, pilih "Simpan sebagai" → Excel Workbook (.xlsx).']);
        }
        try {
            $hasil = $this->svc->periksa($di, $request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->withErrors(['file' => $e instanceof \RuntimeException ? $e->getMessage() : 'File tidak bisa dibaca. Pastikan memakai template dari IRIS dan disimpan sebagai .xlsx.']);
        }
        $token = Str::random(32);
        Cache::put('impor-pintu:'.$token, ['di' => $di->id, 'user' => $request->user()->id, 'hasil' => $hasil], now()->addMinutes(30));

        return view('pintu.master.impor', ['di' => $di, 'hasil' => $hasil, 'token' => $token, 'jumlahPintu' => $di->pintuAir()->count()]);
    }

    public function terapkan(Request $request, DaerahIrigasi $di)
    {
        $this->izinkan($request, $di);
        $simpan = Cache::pull('impor-pintu:'.$request->input('token'));
        if (! $simpan || $simpan['di'] !== $di->id || $simpan['user'] !== $request->user()->id) {
            return redirect()->route('master.pintu-air.impor', ['di' => $di->id])->withErrors(['file' => 'Hasil pemeriksaan sudah kedaluwarsa. Unggah ulang file.']);
        }
        if ($simpan['hasil']['galat']) {
            return redirect()->route('master.pintu-air.impor', ['di' => $di->id])->withErrors(['file' => 'Masih ada baris bergalat. Perbaiki file lalu unggah ulang.']);
        }
        $n = $this->svc->terapkan($di, $simpan['hasil']);

        return redirect()->route('pintu.index', ['di' => $di->id])->with('ok', "Impor selesai: {$n['baru']} pintu baru, {$n['ubah']} pintu diubah.");
    }

    private function izinkan(Request $request, DaerahIrigasi $di): void
    {
        abort_unless($request->user()->bolehKelolaPintu($di->id), 403, 'Anda tidak punya izin mengelola pintu air di D.I. ini.');
    }
}
