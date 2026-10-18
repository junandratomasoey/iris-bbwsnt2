<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Models\WilayahSatgas;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PenggunaController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = User::with('daerahIrigasi:id,nama', 'wilayahSatgas:id,kode')->withCount('subDaerahIrigasi')
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('username', 'like', '%'.$request->q.'%')->orWhere('no_hp', 'like', '%'.$request->q.'%')))
            ->when($request->input('status') === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($request->input('status') === 'belum_login', fn ($q) => $q->whereNull('last_login_at'))
            ->orderBy('role')->orderBy('name')->paginate(30)->withQueryString();

        return view('pengguna.index', ['pengguna' => $pengguna, 'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama'])]);
    }

    public function create()
    {
        return view('pengguna.form', ['pengguna' => new User(['role' => Role::PETUGAS, 'is_active' => true])] + $this->opsi());
    }

    public function store(Request $request)
    {
        $data = $this->validasi($request);
        $password = ($data['password'] ?? null) ?: Str::password(8, symbols: false);
        $user = User::create(collect($data)->except(['sub_ids', 'password'])->all() + ['password' => $password, 'must_change_password' => true]);
        $user->subDaerahIrigasi()->sync($data['sub_ids'] ?? []);

        return redirect()->route('pengguna.index')
            ->with('ok', "Akun {$user->username} dibuat.")
            ->with('password_baru', ['username' => $user->username, 'password' => $password, 'wa' => $user->waLink($this->pesanAkun($user->username, $password))]);
    }

    public function edit(User $pengguna)
    {
        $pengguna->load('subDaerahIrigasi:id');

        return view('pengguna.form', compact('pengguna') + $this->opsi());
    }

    public function update(Request $request, User $pengguna)
    {
        $data = $this->validasi($request, $pengguna);
        $isi = collect($data)->except(['sub_ids', 'password'])->all();
        if (! empty($data['password'])) {
            $isi['password'] = $data['password'];
            $isi['must_change_password'] = true;
        }
        $pengguna->update($isi);
        $pengguna->subDaerahIrigasi()->sync($data['sub_ids'] ?? []);

        return redirect()->route('pengguna.index')->with('ok', "Akun {$pengguna->username} diperbarui.");
    }

    public function destroy(Request $request, User $pengguna)
    {
        abort_if($pengguna->id === $request->user()->id, 422, 'Tidak bisa menghapus akun sendiri.');
        if ($pengguna->laporanHarian()->exists()) {
            $pengguna->update(['is_active' => false]);

            return back()->with('ok', "{$pengguna->name} sudah punya laporan, jadi akunnya dinonaktifkan (bukan dihapus).");
        }
        $pengguna->delete();

        return back()->with('ok', 'Akun dihapus.');
    }

    public function resetPassword(User $pengguna)
    {
        $this->cegahUbahSuperAdmin($pengguna);
        $password = Str::password(8, symbols: false);
        $pengguna->update(['password' => $password, 'must_change_password' => true]);

        return back()->with('password_baru', [
            'username' => $pengguna->username, 'password' => $password,
            'wa' => $pengguna->waLink($this->pesanAkun($pengguna->username, $password)),
        ]);
    }

    private function validasi(Request $request, ?User $user = null): array
    {
        if ($user) {
            $this->cegahUbahSuperAdmin($user);
        }
        $roles = array_keys(Role::LABELS);
        if (! $request->user()->hasRole(Role::SUPER_ADMIN)) {
            $roles = array_diff($roles, [Role::SUPER_ADMIN]);
        }
        $request->merge(['no_hp' => normalisasi_hp($request->input('no_hp'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'no_hp' => ['nullable', 'string', 'min:9', 'max:15'],
            'role' => ['required', Rule::in($roles)],
            'jabatan' => ['nullable', 'string', 'max:50'],
            'wilayah_satgas_id' => ['nullable', 'required_if:role,'.Role::KOORDINATOR, 'exists:wilayah_satgas,id'],
            'daerah_irigasi_id' => ['nullable', 'required_if:role,'.Role::MANAGER, 'exists:daerah_irigasi,id'],
            'status_kontak' => ['nullable', 'string', 'max:30'],
            'is_active' => ['boolean'],
            'password' => ['nullable', 'string', 'min:8'],
            'sub_ids' => ['nullable', 'array'],
            'sub_ids.*' => ['integer', 'exists:sub_daerah_irigasi,id'],
        ], [
            'username.regex' => 'Username hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung.',
            'wilayah_satgas_id.required_if' => 'Pilih wilayah Satgas untuk Koordinator.',
            'daerah_irigasi_id.required_if' => 'Pilih D.I. untuk Manager.',
        ]) + ['is_active' => $request->boolean('is_active')];
    }

    private function cegahUbahSuperAdmin(User $target): void
    {
        abort_if($target->hasRole(Role::SUPER_ADMIN) && ! request()->user()->hasRole(Role::SUPER_ADMIN), 403);
    }

    private function pesanAkun(string $username, string $password): string
    {
        return "Akun IRIS-BBWSNTII Anda:\nUsername: $username\nPassword awal: $password\nLogin di ".url('/login').' lalu ganti password.';
    }

    private function opsi(): array
    {
        return [
            'roles' => request()->user()->hasRole(Role::SUPER_ADMIN) ? Role::LABELS : collect(Role::LABELS)->except(Role::SUPER_ADMIN)->all(),
            'satgasList' => WilayahSatgas::orderBy('id')->get(),
            'diList' => DaerahIrigasi::orderBy('nama')->get(['id', 'nama']),
            'subList' => SubDaerahIrigasi::with('daerahIrigasi:id,nama')->orderBy('daerah_irigasi_id')->orderBy('nama')->get(['id', 'nama', 'daerah_irigasi_id']),
        ];
    }
}
