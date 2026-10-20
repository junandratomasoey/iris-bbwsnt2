<?php

namespace App\Http\Controllers;

use App\Models\DaerahIrigasi;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Support\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Direktori & pengelolaan kontak petugas lapangan (akun petugas, POB, manager).
 * Admin OP/Super Admin: semua. Koordinator: petugas & POB di wilayah Satgas-nya. Manager: di D.I.-nya.
 * Untuk role lain (pimpinan, koordinator, admin) gunakan menu Pengguna.
 */
class PetugasController extends Controller
{
    public const STATUS_KONTAK = ['Belum Dihubungi', 'Sudah Dihubungi', 'Tidak Aktif'];

    public const JABATAN = ['PPA', 'POB', 'Juru', 'Pengamat', 'Manager'];

    public function index(Request $request)
    {
        $u = $request->user();
        $kelola = $this->bolehMengelola($u);
        abort_unless($kelola || $u->boleh('lihat-petugas'), 403);
        $status = $kelola ? $request->input('status', 'aktif') : 'aktif';
        // Super Admin, Pimpinan, Admin OP melihat semua; jabatan lain hanya kontak di D.I. wilayahnya
        $diIds = $u->isGlobal() ? null : ($u->diIdsTerjangkau() ?? []);

        $petugas = User::lapangan()
            ->when($diIds !== null, fn ($q) => $q->where(fn ($w) => $w->whereIn('daerah_irigasi_id', $diIds)
                ->orWhereHas('subDaerahIrigasi', fn ($s) => $s->whereIn('daerah_irigasi_id', $diIds))))
            ->with(['daerahIrigasi:id,nama,kabupaten_id', 'daerahIrigasi.kabupaten:id,nama', 'subDaerahIrigasi:id,nama,daerah_irigasi_id'])
            ->when($status === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($status === 'nonaktif', fn ($q) => $q->where('is_active', false))
            ->when($status === 'belum_login', fn ($q) => $q->where('is_active', true)->whereNull('last_login_at'))
            ->when($request->input('lingkup') === 'saya' && ($ids = $this->diKelola($u)) !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $ids))
            ->when($request->filled('di'), fn ($q) => $q->where('daerah_irigasi_id', $request->integer('di')))
            ->when($request->filled('jabatan'), fn ($q) => $q->where('jabatan', $request->input('jabatan')))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')->orWhere('no_hp', 'like', '%'.normalisasi_hp($request->q).'%')))
            ->orderBy('daerah_irigasi_id')->orderByRaw("CASE jabatan WHEN 'Manager' THEN 0 WHEN 'POB' THEN 1 ELSE 2 END")->orderBy('name')
            ->paginate(51)->withQueryString();

        return view('petugas.index', [
            'petugas' => $petugas,
            'kelola' => $kelola,
            'status' => $status,
            'boleh' => fn (User $p) => $this->bolehUbah($u, $p),
            'diList' => DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))->orderBy('nama')->get(['id', 'nama']),
            'semua' => $diIds === null,
            'jabatanList' => User::lapangan()->whereNotNull('jabatan')->distinct()->orderBy('jabatan')->pluck('jabatan'),
        ]);
    }

    public function create(Request $request)
    {
        abort_unless($this->bolehMengelola($request->user()), 403);
        $diAwal = $request->integer('di') ?: ($request->user()->hasRole(Role::MANAGER) ? $request->user()->daerah_irigasi_id : null);

        return view('petugas.form', ['p' => new User(['role' => Role::PETUGAS, 'jabatan' => 'PPA', 'is_active' => true, 'daerah_irigasi_id' => $diAwal])] + $this->opsi($request->user()));
    }

    public function store(Request $request)
    {
        $u = $request->user();
        abort_unless($this->bolehMengelola($u), 403);
        $data = $this->validasi($request);
        $password = Str::password(8, symbols: false);

        $p = User::create(collect($data)->except('sub_ids')->all() + [
            'username' => $this->usernameUnik($data['no_hp']),
            'password' => $password,
            'must_change_password' => true,
        ]);
        $p->subDaerahIrigasi()->sync($data['sub_ids'] ?? []);

        return redirect()->route('petugas.index', ['di' => $p->daerah_irigasi_id])
            ->with('ok', "Kontak {$p->name} ditambahkan. Login memakai nomor HP {$p->no_hp}.")
            ->with('password_baru', $this->infoPassword($p, $password));
    }

    public function edit(Request $request, User $petugas)
    {
        abort_unless($this->bolehUbah($request->user(), $petugas), 403);
        $petugas->load('subDaerahIrigasi:id,nama,daerah_irigasi_id', 'subDaerahIrigasi.daerahIrigasi:id,nama');

        return view('petugas.form', ['p' => $petugas] + $this->opsi($request->user()));
    }

    public function update(Request $request, User $petugas)
    {
        abort_unless($this->bolehUbah($request->user(), $petugas), 403);
        $data = $this->validasi($request, $petugas);
        $petugas->update(collect($data)->except('sub_ids')->all());

        // penugasan di D.I. lain (yang tidak tampil di form) tetap dipertahankan
        $lain = $petugas->subDaerahIrigasi()->where('daerah_irigasi_id', '!=', $data['daerah_irigasi_id'])->pluck('sub_daerah_irigasi.id')->all();
        $petugas->subDaerahIrigasi()->sync(array_unique([...($data['sub_ids'] ?? []), ...$lain]));

        return redirect()->route('petugas.index', ['di' => $petugas->daerah_irigasi_id, 'status' => $petugas->is_active ? 'aktif' : 'nonaktif'])
            ->with('ok', "Kontak {$petugas->name} diperbarui.");
    }

    public function destroy(Request $request, User $petugas)
    {
        abort_unless($request->user()->can('kelola-pengguna') && $this->bolehUbah($request->user(), $petugas), 403);
        if ($petugas->laporanHarian()->exists()) {
            $petugas->update(['is_active' => false]);

            return redirect()->route('petugas.index')->with('ok', "{$petugas->name} sudah punya laporan, jadi dinonaktifkan (bukan dihapus).");
        }
        $petugas->subDaerahIrigasi()->detach();
        $petugas->delete();

        return redirect()->route('petugas.index')->with('ok', 'Kontak dihapus.');
    }

    public function resetPassword(Request $request, User $petugas)
    {
        abort_unless($this->bolehUbah($request->user(), $petugas), 403);
        $password = Str::password(8, symbols: false);
        $petugas->update(['password' => $password, 'must_change_password' => true, 'is_active' => true]);

        return back()->with('password_baru', $this->infoPassword($petugas, $password));
    }

    // ---------- aturan akses ----------

    private function bolehMengelola(User $u): bool
    {
        return $u->boleh('kelola-petugas') && $this->diKelola($u) !== [];
    }

    /** ID D.I. yang kontaknya boleh dikelola. null = semua. */
    private function diKelola(User $u): ?array
    {
        if ($u->isGlobal()) {
            return null;
        }

        return once(fn () => match (true) {
            $u->hasRole(Role::KOORDINATOR) && (bool) $u->wilayah_satgas_id => DaerahIrigasi::whereHas('kabupaten', fn ($k) => $k->where('wilayah_satgas_id', $u->wilayah_satgas_id))->pluck('id')->all(),
            (bool) $u->daerah_irigasi_id => [$u->daerah_irigasi_id],
            default => [],
        });
    }

    /** Role yang boleh diberikan. Manager hanya oleh Admin OP / Super Admin. */
    private function roleBoleh(User $u): array
    {
        // Manager (dan D.I./Sub-D.I.-nya) hanya diatur Super Admin, Pimpinan, Admin OP
        return $u->boleh('kelola-pengguna') || ($u->isGlobal() && $u->boleh('kelola-petugas'))
            ? [Role::PETUGAS, Role::POB, Role::MANAGER] : [Role::PETUGAS, Role::POB];
    }

    private function bolehUbah(User $u, User $target): bool
    {
        if (! $this->bolehMengelola($u) || $target->id === $u->id || ! in_array($target->role, $this->roleBoleh($u), true)) {
            return false;
        }
        $ids = $this->diKelola($u);

        if ($ids === null || in_array($target->daerah_irigasi_id, $ids)) {
            return true;
        }
        $subs = $target->relationLoaded('subDaerahIrigasi') ? $target->subDaerahIrigasi : $target->subDaerahIrigasi()->get(['sub_daerah_irigasi.id', 'daerah_irigasi_id']);

        return $subs->contains(fn ($s) => in_array($s->daerah_irigasi_id, $ids));
    }

    private function validasi(Request $request, ?User $p = null): array
    {
        $u = $request->user();
        $request->merge(['no_hp' => normalisasi_hp($request->input('no_hp'))]);
        $diIds = $this->diKelola($u);
        $hpBerubah = ! $p || $p->no_hp !== $request->input('no_hp');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'no_hp' => array_filter(['required', 'string', 'min:9', 'max:15', $hpBerubah ? Rule::unique('users', 'no_hp') : null]),
            'jabatan' => ['nullable', 'string', 'max:50'],
            'role' => ['required', Rule::in($this->roleBoleh($u))],
            'daerah_irigasi_id' => ['required', $diIds === null ? 'exists:daerah_irigasi,id' : Rule::in($diIds)],
            'sub_ids' => ['nullable', 'array'],
            'sub_ids.*' => ['integer', Rule::exists('sub_daerah_irigasi', 'id')->where('daerah_irigasi_id', $request->integer('daerah_irigasi_id'))],
            'status_kontak' => ['nullable', Rule::in(self::STATUS_KONTAK)],
        ], [
            'no_hp.required' => 'Nomor HP wajib diisi; dipakai untuk login dan WhatsApp.',
            'no_hp.unique' => 'Nomor HP ini sudah dipakai kontak lain.',
            'no_hp.min' => 'Nomor HP terlalu pendek.',
            'daerah_irigasi_id.required' => 'Pilih D.I. tempat bertugas.',
            'daerah_irigasi_id.in' => 'D.I. ini di luar wilayah Anda.',
            'sub_ids.*.exists' => 'Sub-D.I. harus termasuk D.I. yang dipilih.',
            'role.in' => 'Peran ini tidak bisa Anda berikan.',
        ]);

        return $data + ['is_active' => $request->boolean('is_active')];
    }

    private function usernameUnik(string $hp): string
    {
        $nama = $hp;
        for ($i = 2; User::where('username', $nama)->exists(); $i++) {
            $nama = "$hp-$i";
        }

        return $nama;
    }

    private function infoPassword(User $p, string $password): array
    {
        $pesan = "Akun IRIS-BBWSNTII Anda:\nLogin: {$p->no_hp}\nPassword awal: $password\nBuka ".url('/login').' lalu ganti password.';

        return ['username' => $p->no_hp ?: $p->username, 'password' => $password, 'wa' => $p->waLink($pesan)];
    }

    private function opsi(User $u): array
    {
        $diIds = $this->diKelola($u);

        return [
            'roles' => collect(Role::LABELS)->only($this->roleBoleh($u))->all(),
            'diList' => DaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('id', $diIds))->orderBy('nama')->get(['id', 'nama']),
            'subList' => SubDaerahIrigasi::when($diIds !== null, fn ($q) => $q->whereIn('daerah_irigasi_id', $diIds))->orderBy('nama')->get(['id', 'nama', 'daerah_irigasi_id', 'lokasi']),
            'statusKontak' => self::STATUS_KONTAK,
            'jabatanSaran' => self::JABATAN,
        ];
    }
}
