<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\User;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDanKejadianTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DaerahIrigasi $di;

    protected function setUp(): void
    {
        parent::setUp();
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Tilong', 'kabupaten_id' => $kab->id]);
        $this->admin = User::create(['name' => 'Admin OP', 'username' => 'adminop', 'password' => 'x', 'role' => Role::ADMIN_OP, 'must_change_password' => false]);
    }

    public function test_jadwal_tanam_tidak_boleh_tumpang_tindih(): void
    {
        $data = ['daerah_irigasi_id' => $this->di->id, 'tahun' => 2026, 'mt' => 1, 'mulai' => '2026-11-01', 'selesai' => '2027-02-28', 'komoditas' => 'Padi', 'sumber' => 'Bidang OP'];
        $this->actingAs($this->admin)->post('/master/jadwal-tanam', $data)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/master/jadwal-tanam', ['mt' => 2, 'mulai' => '2027-02-01', 'selesai' => '2027-05-31'] + $data)->assertSessionHasErrors('mulai');
        $this->actingAs($this->admin)->post('/master/jadwal-tanam', ['mt' => 2, 'mulai' => '2027-03-01', 'selesai' => '2027-06-30'] + $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->di->fresh()->mtBerjalan(Carbon::parse('2026-12-15'))->mt);
        $this->assertSame(2, $this->di->fresh()->mtBerjalan(Carbon::parse('2027-04-01'))->mt);
    }

    public function test_kejadian_dilaporkan_lalu_ditangani(): void
    {
        $petugas = User::create(['name' => 'PPA', 'username' => '0812', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false]);
        $this->actingAs($petugas)->post('/kejadian', [
            'tanggal_lapor' => today()->toDateString(), 'kabupaten_id' => $this->di->kabupaten_id, 'jenis_kegagalan' => 'Gagal Tanam',
            'status_kegagalan' => 'Potensi', 'luas_terdampak_ha' => 12.5, 'lat' => -10.1, 'lng' => 123.7,
        ])->assertSessionHasNoErrors();
        $k = Kejadian::first();
        $this->assertSame('Perlu Penanganan', $k->status_penanganan);
        $this->actingAs($petugas)->patch(route('kejadian.penanganan', $k), ['status_penanganan' => 'Selesai Ditangani', 'luas_tertangani_ha' => 12.5])->assertForbidden();
        $this->actingAs($this->admin)->patch(route('kejadian.penanganan', $k), ['status_penanganan' => 'Selesai Ditangani', 'luas_tertangani_ha' => 20])->assertSessionHasErrors('luas_tertangani_ha');
        $this->actingAs($this->admin)->patch(route('kejadian.penanganan', $k), ['status_penanganan' => 'Selesai Ditangani', 'luas_tertangani_ha' => 12.5])->assertSessionHasNoErrors();
        $this->assertSame('Selesai Ditangani', $k->fresh()->status_penanganan);
    }

    public function test_admin_membuat_akun_petugas_dengan_password_otomatis(): void
    {
        $sub = $this->di->subDaerahIrigasi()->create(['nama' => 'Oenitas']);
        $this->actingAs($this->admin)->post('/pengguna', [
            'name' => 'Yakobus Ndolu', 'username' => '082199658042', 'no_hp' => '+62 821-9965-8042', 'role' => Role::PETUGAS,
            'jabatan' => 'PPA', 'daerah_irigasi_id' => $this->di->id, 'is_active' => 1, 'sub_ids' => [$sub->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('password_baru');
        $u = User::where('username', '082199658042')->first();
        $this->assertSame('082199658042', $u->no_hp);
        $this->assertTrue($u->must_change_password);
        $this->assertSame([$sub->id], $u->subIdsPelaporan());
    }

    public function test_admin_op_tidak_bisa_membuat_super_admin(): void
    {
        $this->actingAs($this->admin)->post('/pengguna', ['name' => 'X', 'username' => 'x1', 'role' => Role::SUPER_ADMIN, 'is_active' => 1])->assertSessionHasErrors('role');
    }

    public function test_aktivitas_tercatat(): void
    {
        $this->actingAs($this->admin)->put(route('master.daerah-irigasi.update', $this->di), ['kode' => 'DI-01', 'nama' => 'Tilong Baru', 'kewenangan' => 'Pusat']);
        $log = ActivityLog::where('action', 'diubah')->latest('id')->first();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame('Tilong Baru', $log->changes['baru']['nama']);
    }
}
