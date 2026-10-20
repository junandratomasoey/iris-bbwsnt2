<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Models\WilayahSatgas;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KontakPetugasTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $diA;

    private DaerahIrigasi $diB;

    private SubDaerahIrigasi $subA;

    private SubDaerahIrigasi $subB;

    private User $admin;

    private User $koordinator;

    private User $manager;

    private User $petugasA;

    private User $petugasB;

    protected function setUp(): void
    {
        parent::setUp();
        $w1 = WilayahSatgas::create(['kode' => 'II', 'nama' => 'Satgas II', 'cakupan' => 'Timor']);
        $w2 = WilayahSatgas::create(['kode' => 'III', 'nama' => 'Satgas III', 'cakupan' => 'Sumba']);
        $kupang = Kabupaten::create(['nama' => 'Kupang', 'wilayah_satgas_id' => $w1->id]);
        $sumba = Kabupaten::create(['nama' => 'Sumba Timur', 'wilayah_satgas_id' => $w2->id]);
        $this->diA = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kupang->id]);
        $this->diB = DaerahIrigasi::create(['kode' => 'DI-02', 'nama' => 'Kambaniru', 'kabupaten_id' => $sumba->id]);
        $this->subA = $this->diA->subDaerahIrigasi()->create(['nama' => 'Dalam Kom I']);
        $this->subB = $this->diB->subDaerahIrigasi()->create(['nama' => 'Kambaniru Kanan']);

        $buat = fn ($nama, $role, $extra = []) => User::create(['name' => $nama, 'username' => strtolower(str_replace(' ', '', $nama)), 'password' => 'x', 'role' => $role, 'must_change_password' => false] + $extra);
        $this->admin = $buat('Admin', Role::ADMIN_OP);
        $this->koordinator = $buat('Koor Timor', Role::KOORDINATOR, ['wilayah_satgas_id' => $w1->id]);
        $this->manager = $buat('Manager Oesao', Role::MANAGER, ['daerah_irigasi_id' => $this->diA->id]);
        $this->petugasA = $buat('Petugas A', Role::PETUGAS, ['daerah_irigasi_id' => $this->diA->id, 'no_hp' => '081200000001']);
        $this->petugasB = $buat('Petugas B', Role::PETUGAS, ['daerah_irigasi_id' => $this->diB->id, 'no_hp' => '081200000002']);
        $this->petugasB->subDaerahIrigasi()->attach($this->subB);
    }

    public function test_kontak_hanya_di_di_wilayahnya_kecuali_pimpinan_dan_admin(): void
    {
        $pimpinan = User::create(['name' => 'Kasatker', 'username' => 'kasatker', 'password' => 'x', 'role' => Role::PIMPINAN, 'must_change_password' => false]);
        foreach ([$this->admin, $pimpinan] as $u) {
            $this->actingAs($u)->get(route('petugas.index'))->assertOk()->assertSee('Petugas A')->assertSee('Petugas B');
        }
        $this->actingAs($this->manager)->get(route('petugas.index'))->assertOk()
            ->assertSee('Petugas A')->assertDontSee('Petugas B')->assertSee('di D.I. wilayah Anda (Oesao)');
        $this->actingAs($this->koordinator)->get(route('petugas.index'))->assertOk()->assertSee('Petugas A')->assertDontSee('Petugas B');   // Satgas II = Kupang
        $this->actingAs($this->manager)->get(route('petugas.index', ['di' => $this->diB->id]))->assertOk()->assertDontSee('Petugas B');   // filter D.I. lain tidak membuka
    }

    public function test_admin_menambah_petugas_dengan_login_nomor_hp(): void
    {
        $this->actingAs($this->admin)->post(route('petugas.store'), [
            'name' => 'Yohanes Baru', 'no_hp' => '+62 812-3456-7890', 'jabatan' => 'PPA', 'role' => Role::PETUGAS,
            'daerah_irigasi_id' => $this->diA->id, 'sub_ids' => [$this->subA->id], 'is_active' => 1,
        ])->assertSessionHasNoErrors()->assertRedirect()->assertSessionHas('password_baru');

        $u = User::where('name', 'Yohanes Baru')->firstOrFail();
        $this->assertSame('081234567890', $u->no_hp);
        $this->assertSame('081234567890', $u->username);
        $this->assertTrue($u->must_change_password);
        $this->assertSame([$this->subA->id], $u->subDaerahIrigasi()->pluck('sub_daerah_irigasi.id')->all());
        $this->assertStringContainsString('wa.me/6281234567890', session('password_baru')['wa']);
    }

    public function test_nomor_hp_tidak_boleh_ganda_dan_sub_harus_di_di_terpilih(): void
    {
        $this->actingAs($this->admin)->post(route('petugas.store'), [
            'name' => 'Ganda', 'no_hp' => '0812 0000 0001', 'role' => Role::PETUGAS,
            'daerah_irigasi_id' => $this->diA->id, 'sub_ids' => [$this->subB->id],
        ])->assertSessionHasErrors(['no_hp', 'sub_ids.0']);
    }

    public function test_koordinator_dan_manager_hanya_mengelola_wilayahnya(): void
    {
        $this->actingAs($this->koordinator)->get(route('petugas.edit', $this->petugasA))->assertOk();
        $this->actingAs($this->koordinator)->get(route('petugas.edit', $this->petugasB))->assertForbidden();
        $this->actingAs($this->manager)->get(route('petugas.edit', $this->petugasB))->assertForbidden();

        // tidak bisa memindahkan petugas ke D.I. di luar wilayah, atau mengangkat jadi manager
        $this->actingAs($this->koordinator)->put(route('petugas.update', $this->petugasA), [
            'name' => 'Petugas A', 'no_hp' => '081200000001', 'role' => Role::MANAGER, 'daerah_irigasi_id' => $this->diB->id,
        ])->assertSessionHasErrors(['role', 'daerah_irigasi_id']);

        $this->actingAs($this->manager)->put(route('petugas.update', $this->petugasA), [
            'name' => 'Petugas A Revisi', 'no_hp' => '081200000001', 'role' => Role::POB, 'jabatan' => 'POB',
            'daerah_irigasi_id' => $this->diA->id, 'sub_ids' => [$this->subA->id], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Petugas A Revisi', $this->petugasA->fresh()->name);

        // hanya Admin OP / Super Admin yang bisa menghapus
        $this->actingAs($this->manager)->delete(route('petugas.destroy', $this->petugasA))->assertForbidden();
        // petugas biasa tidak bisa membuka form
        $this->actingAs($this->petugasA)->get(route('petugas.create'))->assertForbidden();
    }

    public function test_penugasan_di_lain_dipertahankan_dan_hapus_jadi_nonaktif_bila_ada_laporan(): void
    {
        $this->petugasB->subDaerahIrigasi()->attach($this->subA); // juga bertugas di Oesao
        $this->actingAs($this->admin)->put(route('petugas.update', $this->petugasB), [
            'name' => 'Petugas B', 'no_hp' => '081200000002', 'role' => Role::PETUGAS,
            'daerah_irigasi_id' => $this->diB->id, 'sub_ids' => [], 'is_active' => 1,
        ])->assertSessionHasNoErrors();
        $this->assertSame([$this->subA->id], $this->petugasB->subDaerahIrigasi()->pluck('sub_daerah_irigasi.id')->all());

        LaporanHarian::create(['tanggal' => today(), 'sub_daerah_irigasi_id' => $this->subB->id, 'user_id' => $this->petugasB->id,
            'debit_intake_lps' => 1, 'tma_cm' => 1, 'kondisi_saluran' => 'normal']);
        $this->actingAs($this->admin)->delete(route('petugas.destroy', $this->petugasB));
        $this->assertFalse($this->petugasB->fresh()->is_active);

        $this->actingAs($this->admin)->delete(route('petugas.destroy', $this->petugasA));
        $this->assertNull(User::find($this->petugasA->id));
    }

    public function test_reset_password_dan_direktori(): void
    {
        $this->actingAs($this->koordinator)->post(route('petugas.reset', $this->petugasA))->assertSessionHas('password_baru');
        $this->assertTrue($this->petugasA->fresh()->must_change_password);

        $this->actingAs($this->koordinator)->get(route('petugas.index'))->assertOk()
            ->assertSee('Petugas A')->assertDontSee('Petugas B')->assertSee(route('petugas.edit', $this->petugasA));   // hanya kontak di D.I. wilayah Satgas-nya
    }
}
