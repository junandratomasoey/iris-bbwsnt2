<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\IzinJabatan;
use App\Models\Kabupaten;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Support\HakAkses;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Super Admin & Admin OP boleh mengubah/menghapus semua laporan petugas, termasuk debit/TMA tiap pintu. */
class KelolaLaporanTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private SubDaerahIrigasi $sub;

    private User $sa;

    private User $adminOp;

    private User $manager;

    private User $ppaA;

    private User $ppaB;

    private PintuAir $t1;

    private PintuAir $t2;

    protected function setUp(): void
    {
        parent::setUp();
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id]);
        $this->sub = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kiri', 'luas_ha' => 300]);
        $buat = fn ($role, $u, $di = true) => User::create(['name' => ucfirst($u), 'username' => $u, 'password' => 'x', 'role' => $role,
            'must_change_password' => false, 'daerah_irigasi_id' => $di ? $this->di->id : null]);
        $this->sa = $buat(Role::SUPER_ADMIN, 'sa', false);
        $this->adminOp = $buat(Role::ADMIN_OP, 'adminop', false);
        $this->manager = $buat(Role::MANAGER, 'manager');
        $this->ppaA = $buat(Role::PETUGAS, 'ppaa');
        $this->ppaB = $buat(Role::PETUGAS, 'ppab');
        $this->ppaA->subDaerahIrigasi()->attach($this->sub);
        $this->ppaB->subDaerahIrigasi()->attach($this->sub);

        $p = fn ($kode) => PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $this->sub->id,
            'kode' => $kode, 'jenis' => 'tersier', 'tingkat' => 'tersier', 'luas_layanan_ha' => 50]);
        $this->t1 = $p('BOs.1 ki');
        $this->t2 = $p('BOs.1 ka');
        $this->t1->petugas()->attach($this->ppaA, ['peran' => 'utama']);
        $this->t2->petugas()->attach($this->ppaB, ['peran' => 'utama']);
    }

    private function laporan(array $baca, array $ubah = []): array
    {
        return $ubah + [
            'tanggal' => today()->toDateString(), 'jenis' => 'rutin', 'sub_daerah_irigasi_id' => $this->sub->id, 'baca' => $baca,
            'petak' => [$this->t1->id => ['jenis_tanaman' => 'P', 'luas_tanam_ha' => 40, 'fase' => 3, 'kecukupan_air' => 'C', 'luas_kering_ha' => 0]],
        ];
    }

    public function test_admin_op_mengubah_laporan_tanaman_dan_pintu_milik_ppa_yang_sudah_disetujui(): void
    {
        $this->actingAs($this->ppaA)->post(route('laporan.store'), $this->laporan([$this->t1->id => ['debit' => 35, 'tma' => 22]]))->assertSessionHasNoErrors();
        $l = LaporanHarian::firstOrFail();
        $l->update(['status_verifikasi' => 'disetujui']);

        // pemilik tidak bisa lagi, PPA lain & Manager tidak bisa
        $this->actingAs($this->ppaA)->get(route('laporan.edit', $l))->assertForbidden();
        $this->actingAs($this->ppaB)->get(route('laporan.edit', $l))->assertForbidden();
        $this->actingAs($this->manager)->put(route('laporan.update', $l), $this->laporan([$this->t1->id => ['debit' => 1, 'tma' => 1]]))->assertForbidden();

        // Admin OP: koreksi debit/TMA pintu petak + tambah pintu tetangga (milik PPA B)
        $this->actingAs($this->adminOp)->get(route('laporan.edit', $l))->assertOk()->assertSee('BOs.1 ka');
        $this->actingAs($this->adminOp)->put(route('laporan.update', $l), $this->laporan([
            $this->t1->id => ['debit' => 30, 'tma' => 20], $this->t2->id => ['debit' => 55, 'tma' => 31],
        ], ['petak' => [$this->t1->id => ['jenis_tanaman' => 'P', 'luas_tanam_ha' => 40, 'fase' => 3, 'kecukupan_air' => 'KR', 'luas_kering_ha' => 5]]]))->assertSessionHasNoErrors();

        $l->refresh();
        $this->assertSame($this->ppaA->id, $l->user_id);                 // tetap laporan PPA A
        $this->assertSame('disetujui', $l->status_verifikasi);
        $this->assertSame(5.0, (float) $l->luas_kering_ha);
        $this->assertSame('waspada', $l->status());
        $this->assertSame(30.0, PembacaanPintu::where('pintu_air_id', $this->t1->id)->value('debit_lps') + 0.0);
        $this->assertSame(31.0, PembacaanPintu::where('pintu_air_id', $this->t2->id)->value('tma_cm') + 0.0);
        $this->assertSame($this->ppaA->id, $l->sesiPintu->user_id);

        // sesi pintu harian: tautan ubah lewat laporan untuk admin
        $this->actingAs($this->adminOp)->get(route('pembacaan-pintu.show', $l->sesiPintu))->assertOk()->assertSee('Ubah lewat laporan PPA');

        // Super Admin menghapus
        $this->actingAs($this->sa)->delete(route('laporan.destroy', $l))->assertRedirect();
        $this->assertSame(0, LaporanHarian::count());
        $this->assertSame(0, PembacaanPintu::count());
    }

    public function test_admin_op_mengubah_dan_menghapus_laporan_air_lewat_batas_waktu(): void
    {
        $l = LaporanAir::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->manager->id, 'tanggal' => today()->subDays(20),
            'debit_intake_lps' => 900, 'tma_intake_cm' => 60, 'kondisi_saluran' => 'normal']);
        $data = ['daerah_irigasi_id' => $this->di->id, 'tanggal' => $l->tanggal->toDateString(), 'jenis' => 'rutin', 'tma_sungai_cm' => 70, 'debit_intake_lps' => 750, 'tma_intake_cm' => 55];

        $this->actingAs($this->manager)->put(route('laporan-air.update', $l), $data)->assertForbidden();   // lewat 7 hari
        $this->actingAs($this->adminOp)->get(route('laporan-air.show', $l))->assertOk()->assertSee('Ubah');
        $this->actingAs($this->adminOp)->put(route('laporan-air.update', $l), $data)->assertSessionHasNoErrors();
        $this->assertSame(750.0, $l->fresh()->debit_intake_lps);
        $this->assertSame($this->manager->id, $l->fresh()->user_id);
        $this->actingAs($this->adminOp)->delete(route('laporan-air.destroy', $l))->assertRedirect();
        $this->assertSame(0, LaporanAir::count());
    }

    public function test_admin_op_crud_pembacaan_pintu_semua_petugas(): void
    {
        $sesi = SesiPintu::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->ppaB->id, 'tanggal' => today()->subDays(40), 'jenis' => 'rutin']);
        PembacaanPintu::create(['sesi_pintu_id' => $sesi->id, 'pintu_air_id' => $this->t2->id, 'tanggal' => $sesi->tanggal, 'debit_lps' => 50]);
        $data = ['tanggal' => $sesi->tanggal->toDateString(), 'jenis' => 'rutin', 'baca' => [
            $this->t1->id => ['debit' => 44, 'tma' => 21], $this->t2->id => ['debit' => 52, 'tma' => 30],
        ]];

        $this->actingAs($this->ppaA)->put(route('pembacaan-pintu.update', $sesi), $data)->assertForbidden();
        $this->actingAs($this->adminOp)->get(route('pembacaan-pintu.edit', $sesi))->assertOk()->assertSee('BOs.1 ki')->assertSee('BOs.1 ka');
        $this->actingAs($this->adminOp)->put(route('pembacaan-pintu.update', $sesi), $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $sesi->pembacaan()->count());
        $this->assertSame(52.0, PembacaanPintu::where('pintu_air_id', $this->t2->id)->value('debit_lps') + 0.0);

        // tambah pembacaan baru atas nama sendiri, di semua pintu
        $this->actingAs($this->adminOp)->post(route('pembacaan-pintu.store'), ['daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(),
            'jenis' => 'panen', 'baca' => [$this->t2->id => ['debit' => 40, 'tma' => 25]]])->assertSessionHasNoErrors();

        $this->actingAs($this->adminOp)->delete(route('pembacaan-pintu.destroy', $sesi))->assertRedirect();
        $this->assertSame(1, SesiPintu::count());
    }

    public function test_izin_kelola_laporan_bisa_dicabut_di_hak_akses(): void
    {
        $l = LaporanAir::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->manager->id, 'tanggal' => today()->subDays(20), 'debit_intake_lps' => 900, 'tma_intake_cm' => 60, 'kondisi_saluran' => 'normal']);
        IzinJabatan::create(['role' => Role::ADMIN_OP, 'izin' => 'kelola-laporan', 'boleh' => false]);
        HakAkses::lupakan();

        $this->actingAs($this->adminOp)->get(route('laporan-air.edit', $l))->assertForbidden();
        $this->actingAs($this->sa)->get(route('laporan-air.edit', $l))->assertOk();   // Super Admin selalu boleh
    }
}
