<?php

namespace App\Http\Controllers;

use App\Models\IzinJabatan;
use App\Models\IzinPengguna;
use App\Models\User;
use App\Support\HakAkses;
use App\Support\Izin;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Matriks izin jabatan × form/fitur, dan pengecualian per orang. Hanya Super Admin. */
class HakAksesController extends Controller
{
    public function index(Request $request)
    {
        $roles = collect(Role::LABELS)->except(Role::SUPER_ADMIN)->all();
        $pengecualian = IzinPengguna::with('user:id,name,username,role,is_active')->get()
            ->sortBy(fn ($p) => [$p->user->name, $p->izin])->values();
        $jumlah = User::where('is_active', true)->selectRaw('role, count(*) n')->groupBy('role')->pluck('n', 'role');

        return view('hak-akses.index', [
            'roles' => $roles,
            'matriks' => HakAkses::matriks(),
            'pengecualian' => $pengecualian,
            'jumlah' => $jumlah,
            'penggunaList' => User::where('role', '!=', Role::SUPER_ADMIN)->orderBy('name')->get(['id', 'name', 'username', 'role']),
        ]);
    }

    public function update(Request $request)
    {
        $roles = array_keys(collect(Role::LABELS)->except(Role::SUPER_ADMIN)->all());
        $isian = $request->validate(['izin' => ['nullable', 'array']])['izin'] ?? [];
        $berubah = 0;

        DB::transaction(function () use ($roles, $isian, &$berubah) {
            $lama = HakAkses::matriks();
            foreach ($roles as $role) {
                foreach (Izin::kunci() as $izin) {
                    $boleh = (bool) ($isian[$role][$izin] ?? false);
                    if ($lama[$role][$izin] !== $boleh) {
                        $berubah++;
                    }
                    $baris = IzinJabatan::firstOrNew(['role' => $role, 'izin' => $izin]);
                    $baris->boleh = $boleh;
                    $baris->save(); // perubahan tercatat di log aktivitas
                }
            }
        });
        HakAkses::lupakan();

        return redirect()->route('hak-akses.index')->with('ok', $berubah ? "Hak akses jabatan disimpan ($berubah perubahan)." : 'Tidak ada perubahan.');
    }

    public function bawaan()
    {
        IzinJabatan::query()->get()->each->delete();
        HakAkses::lupakan();

        return redirect()->route('hak-akses.index')->with('ok', 'Hak akses jabatan dikembalikan ke bawaan.');
    }

    public function simpanPengecualian(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')->whereNot('role', Role::SUPER_ADMIN)],
            'izin' => ['required', Rule::in(Izin::kunci())],
            'boleh' => ['required', 'boolean'],
            'alasan' => ['nullable', 'string', 'max:255'],
        ], ['user_id.exists' => 'Pilih pengguna (Super Admin selalu punya semua izin).']);

        $user = User::findOrFail($data['user_id']);
        $ikutJabatan = HakAkses::matriks()[$user->role][$data['izin']];
        if ($ikutJabatan === (bool) $data['boleh']) {
            IzinPengguna::where('user_id', $user->id)->where('izin', $data['izin'])->get()->each->delete();

            return back()->with('ok', "{$user->name} sudah ".($ikutJabatan ? 'punya' : 'tidak punya').' izin "'.Izin::label($data['izin']).'" dari jabatannya, jadi tidak perlu pengecualian.');
        }
        IzinPengguna::updateOrCreate(['user_id' => $user->id, 'izin' => $data['izin']], ['boleh' => $data['boleh'], 'alasan' => $data['alasan'] ?? null]);

        return back()->with('ok', ($data['boleh'] ? 'Izin diberikan' : 'Izin dicabut')." untuk {$user->name}: ".Izin::label($data['izin']).'.');
    }

    public function hapusPengecualian(IzinPengguna $pengecualian)
    {
        $pengecualian->load('user:id,name');
        $pengecualian->delete();

        return back()->with('ok', "Pengecualian {$pengecualian->user->name} dihapus; kembali mengikuti jabatan.");
    }
}
