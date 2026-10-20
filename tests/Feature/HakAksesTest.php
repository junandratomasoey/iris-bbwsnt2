<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\IzinPengguna;
use App\Models\Kabupaten;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HakAksesTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;

    private User $admin;

    private User $petugas;

    private User $pob;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Manikin', 'kabupaten_id' => $kab->id]);
        $sub = $di->subDaerahIrigasi()->create(['nama' => 'Air Sagu', 'luas_ha' => 97]);
        $buat = fn ($role, $u) => User::create(['name' => ucfirst($u), 'username' => $u, 'password' => 'x', 'role' => $role, 'must_change_password' => false, 'daerah_irigasi_id' => $di->id]);
        $this->sa = $buat(Role::SUPER_ADMIN, 'sa');
        $this->admin = $buat(Role::ADMIN_OP, 'admin');
        $this->petugas = $buat(Role::PETUGAS, 'ppa');
        $this->pob = $buat(Role::POB, 'pob');
        $this->pimpinan = $buat(Role::PIMPINAN, 'kasatker');
        $this->petugas->subDaerahIrigasi()->attach($sub);
        $this->pob->subDaerahIrigasi()->attach($sub);
    }

    private function segar(User $u): User
    {
        return User::find($u->id);
    }

    public function test_hanya_super_admin_membuka_hak_akses(): void
    {
        $this->actingAs($this->admin)->get(route('hak-akses.index'))->assertForbidden();
        $this->actingAs($this->sa)->get(route('hak-akses.index'))->assertOk()
            ->assertSee('Isi pembacaan pintu air')->assertSee('Pengecualian per orang');
    }

    public function test_matriks_jabatan_mengubah_form_yang_bisa_dibuka(): void
    {
        $this->actingAs($this->petugas)->get(route('laporan.create'))->assertOk();
        $this->actingAs($this->pimpinan)->get(route('kejadian.create'))->assertForbidden();

        // bawaan semua jabatan, lalu: petugas tidak lagi isi laporan harian, pimpinan boleh laporkan kejadian
        $isian = [];
        foreach (\App\Support\HakAkses::matriks() as $role => $izin) {
            foreach ($izin as $k => $b) {
                if ($b) {
                    $isian[$role][$k] = 1;
                }
            }
        }
        unset($isian[Role::PETUGAS]['lapor']);
        $isian[Role::PIMPINAN]['lapor-kejadian'] = 1;
        $this->actingAs($this->sa)->put(route('hak-akses.update'), ['izin' => $isian])
            ->assertSessionHas('ok', 'Hak akses jabatan disimpan (2 perubahan).');

        $this->actingAs($this->segar($this->petugas))->get(route('laporan.create'))->assertForbidden();
        $this->actingAs($this->segar($this->pob))->get(route('laporan.create'))->assertOk();
        $this->actingAs($this->segar($this->pimpinan))->get(route('kejadian.create'))->assertOk();
        $this->assertTrue($this->segar($this->sa)->can('lapor'));   // Super Admin tidak terpengaruh

        $this->actingAs($this->sa)->post(route('hak-akses.bawaan'));
        $this->actingAs($this->segar($this->petugas))->get(route('laporan.create'))->assertOk();
    }

    public function test_pengecualian_per_orang_menimpa_jabatan(): void
    {
        $this->actingAs($this->sa)->post(route('hak-akses.pengecualian.store'), ['user_id' => $this->pob->id, 'izin' => 'lapor', 'boleh' => 0, 'alasan' => 'Khusus pintu air'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->sa)->post(route('hak-akses.pengecualian.store'), ['user_id' => $this->petugas->id, 'izin' => 'verifikasi', 'boleh' => 1]);

        $this->actingAs($this->segar($this->pob))->get(route('laporan.create'))->assertForbidden();
        $this->actingAs($this->segar($this->pob))->get(route('pembacaan-pintu.create'))->assertOk();   // izin lain tetap
        $this->assertTrue($this->segar($this->petugas)->can('verifikasi'));
        $this->assertFalse($this->segar($this->admin)->can('lihat-audit'));

        // pengecualian yang sama dengan jabatan tidak disimpan
        $this->actingAs($this->sa)->post(route('hak-akses.pengecualian.store'), ['user_id' => $this->admin->id, 'izin' => 'lapor', 'boleh' => 1]);
        $this->assertSame(2, IzinPengguna::count());
        // Super Admin tidak bisa diberi pengecualian
        $this->actingAs($this->sa)->post(route('hak-akses.pengecualian.store'), ['user_id' => $this->sa->id, 'izin' => 'lapor', 'boleh' => 0])
            ->assertSessionHasErrors('user_id');

        $this->actingAs($this->sa)->delete(route('hak-akses.pengecualian.destroy', IzinPengguna::where('user_id', $this->pob->id)->first()));
        $this->actingAs($this->segar($this->pob))->get(route('laporan.create'))->assertOk();
    }

    public function test_menu_infrastruktur_peta_batas_p3a_hanya_pimpinan_dan_admin(): void
    {
        $koor = User::create(['name' => 'Koor', 'username' => 'koor', 'password' => 'x', 'role' => Role::KOORDINATOR, 'must_change_password' => false]);
        $mgr = User::create(['name' => 'Mgr', 'username' => 'mgr', 'password' => 'x', 'role' => Role::MANAGER, 'must_change_password' => false]);
        foreach (['master.bangunan.index', 'master.peta.index', 'master.p3a.index'] as $r) {
            foreach ([$this->sa, $this->admin, $this->pimpinan] as $u) {
                $this->actingAs($u)->get(route($r))->assertOk();
            }
            foreach ([$koor, $mgr, $this->petugas, $this->pob] as $u) {
                $this->actingAs($u)->get(route($r))->assertForbidden();
            }
        }
        $this->actingAs($mgr)->get(route('master.jadwal-tanam.index'))->assertOk();        // jadwal tanam tetap
        $this->actingAs($mgr)->get(route('laporan.index'))->assertDontSee('Peta batas D.I.')->assertDontSee('>P3A<', false)->assertSee('Jadwal tanam');
    }

    public function test_menu_mengikuti_izin(): void
    {
        $this->actingAs($this->petugas)->get(route('laporan.index'))->assertOk()
            ->assertDontSee('Infrastruktur')->assertSee('Baca pintu air')->assertDontSee('Hak akses');
        IzinPengguna::create(['user_id' => $this->petugas->id, 'izin' => 'lihat-master', 'boleh' => true]);
        $this->actingAs($this->segar($this->petugas))->get(route('laporan.index'))->assertSee('Infrastruktur');
        $this->actingAs($this->sa)->get(route('laporan.index'))->assertSee('Hak akses');
    }
}
