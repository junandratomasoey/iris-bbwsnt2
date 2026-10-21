<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Services\CadanganService;
use Illuminate\Http\Request;

/** Menu Cadangan data (Super Admin) + unduhan cadangan terbaru untuk laptop/NAS kantor dengan token. */
class CadanganController extends Controller
{
    public function __construct(private CadanganService $svc) {}

    public function index()
    {
        $daftar = $this->svc->daftar();
        $terakhir = $daftar->firstWhere('jenis', 'db');

        return view('cadangan.index', [
            'daftar' => $daftar,
            'terakhir' => $terakhir,
            'terlambat' => ! $terakhir || $terakhir['waktu']->lt(now()->subHours(36)),
            'adaToken' => filled(config('services.cadangan.token')),
            'artisan' => base_path('artisan'),
        ]);
    }

    public function store(Request $request)
    {
        $jenis = $request->validate(['jenis' => ['required', 'in:db,foto']])['jenis'];
        @set_time_limit(600);
        try {
            $nama = $jenis === 'foto' ? $this->svc->buatFoto() : $this->svc->buatDatabase();
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['jenis' => 'Cadangan gagal dibuat: '.$e->getMessage()]);
        }
        ActivityLog::create(['user_id' => $request->user()->id, 'action' => 'cadangan', 'subject_type' => 'Cadangan', 'subject_id' => null,
            'changes' => ['file' => $nama], 'ip_address' => $request->ip()]);

        return back()->with('ok', "Cadangan $nama dibuat.");
    }

    public function unduh(string $nama)
    {
        $path = $this->svc->path($nama);
        abort_unless($path, 404);

        return response()->download($path);
    }

    public function destroy(string $nama)
    {
        $path = $this->svc->path($nama);
        abort_unless($path, 404);
        @unlink($path);

        return back()->with('ok', "Cadangan $nama dihapus.");
    }

    /** Skrip PowerShell untuk menyalin cadangan terbaru ke laptop/NAS kantor (Task Scheduler Windows). */
    public function skrip()
    {
        $isi = view('cadangan.skrip', ['url' => route('cadangan.ambil')])->render();

        return response($isi, 200, ['Content-Type' => 'text/plain; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="ambil-cadangan-iris.ps1"']);
    }

    /** Unduh cadangan database terbaru tanpa login, hanya dengan header X-Cadangan-Token yang cocok dengan CADANGAN_TOKEN di .env. */
    public function ambil(Request $request)
    {
        $token = (string) config('services.cadangan.token');
        abort_unless(strlen($token) >= 32 && hash_equals($token, (string) $request->header('X-Cadangan-Token')), 404);
        $jenis = $request->query('jenis') === 'foto' ? 'foto' : 'db';
        $f = $this->svc->terakhir($jenis);
        abort_unless($f, 404);

        return response()->download($this->svc->path($f['nama']));
    }
}
