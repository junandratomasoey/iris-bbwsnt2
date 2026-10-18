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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AlurLaporanTest extends TestCase
{
    use RefreshDatabase;

    private SubDaerahIrigasi $subA;

    private SubDaerahIrigasi $subB;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $w1 = WilayahSatgas::create(['kode' => 'II', 'nama' => 'Satgas II', 'cakupan' => 'Timor Barat']);
        $w2 = WilayahSatgas::create(['kode' => 'III', 'nama' => 'Satgas III', 'cakupan' => 'Sumba']);
        $kupang = Kabupaten::create(['nama' => 'Kupang', 'wilayah_satgas_id' => $w1->id]);
        $sumba = Kabupaten::create(['nama' => 'Sumba Timur', 'wilayah_satgas_id' => $w2->id]);
        $diA = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kupang->id]);
        $diB = DaerahIrigasi::create(['kode' => 'DI-02', 'nama' => 'Kambaniru', 'kabupaten_id' => $sumba->id]);
        $this->subA = $diA->subDaerahIrigasi()->create(['nama' => 'Dalam Kom I', 'luas_ha' => 275]);
        $this->subB = $diB->subDaerahIrigasi()->create(['nama' => 'Kambaniru', 'luas_ha' => 1000]);
        $this->petugas = User::create(['name' => 'Petugas A', 'username' => '081200000001', 'no_hp' => '081200000001', 'password' => 'Rahasia123', 'role' => Role::PETUGAS, 'must_change_password' => false, 'daerah_irigasi_id' => $diA->id]);
        $this->petugas->subDaerahIrigasi()->attach($this->subA);
    }

    private function dataLaporan(array $ubah = []): array
    {
        return $ubah + [
            'tanggal' => today()->toDateString(), 'sub_daerah_irigasi_id' => $this->subA->id,
            'debit_intake_lps' => 120.5, 'tma_cm' => 45, 'kondisi_saluran' => 'kurang', 'bukaan_pintu_persen' => 60,
            'fase_tanaman' => 'generatif', 'luas_tanam_ha' => 200, 'luas_hijau_ha' => 150, 'luas_kering_ha' => 50,
            'luas_panen_ha' => 0, 'luas_puso_ha' => 0, 'kendala' => 'Debit turun', 'lat' => -10.12, 'lng' => 123.83,
        ];
    }

    public function test_login_dengan_nomor_hp_format_internasional(): void
    {
        $this->post('/login', ['username' => '+62 812-0000-0001', 'password' => 'Rahasia123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->petugas);
    }

    public function test_password_awal_wajib_diganti(): void
    {
        $this->petugas->update(['must_change_password' => true]);
        $this->actingAs($this->petugas)->get('/dashboard')->assertRedirect('/akun/password');
        $this->actingAs($this->petugas)->put('/akun/password', ['password_lama' => 'Rahasia123', 'password' => 'BaruSekali9', 'password_confirmation' => 'BaruSekali9'])->assertRedirect('/dashboard');
        $this->assertFalse($this->petugas->fresh()->must_change_password);
    }

    public function test_petugas_mengirim_laporan_dengan_foto(): void
    {
        $res = $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan([
            'foto' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.jpg', 800, 600)],
        ]));
        $l = LaporanHarian::first();
        $res->assertRedirect(route('laporan.show', $l));
        $this->assertSame('menunggu', $l->status_verifikasi);
        $this->assertSame('siaga', $l->status()); // 25% kering
        $this->assertCount(2, $l->foto);
        Storage::disk('uploads')->assertExists($l->foto->first()->path);
        [$w] = getimagesizefromstring(Storage::disk('uploads')->get($l->foto->first()->path));
        $this->assertLessThanOrEqual(1600, $w, 'foto besar diperkecil');
    }

    public function test_petugas_tidak_bisa_lapor_sub_di_lain(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan(['sub_daerah_irigasi_id' => $this->subB->id]))->assertForbidden();
    }

    public function test_satu_laporan_per_sub_per_hari(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan());
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan())->assertSessionHasErrors('sub_daerah_irigasi_id');
    }

    public function test_luas_kering_tidak_boleh_melebihi_luas_tanam(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan(['luas_kering_ha' => 300]))->assertSessionHasErrors('luas_kering_ha');
    }

    public function test_verifikasi_koordinator_dan_laporan_terkunci(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan());
        $l = LaporanHarian::first();
        $koor = User::create(['name' => 'Koor II', 'username' => 'koor2', 'password' => 'x', 'role' => Role::KOORDINATOR, 'wilayah_satgas_id' => WilayahSatgas::where('kode', 'II')->value('id'), 'must_change_password' => false]);
        $koorLain = User::create(['name' => 'Koor III', 'username' => 'koor3', 'password' => 'x', 'role' => Role::KOORDINATOR, 'wilayah_satgas_id' => WilayahSatgas::where('kode', 'III')->value('id'), 'must_change_password' => false]);

        $this->actingAs($koorLain)->post(route('laporan.verifikasi', $l), ['keputusan' => 'disetujui'])->assertForbidden();
        $this->actingAs($koorLain)->get(route('laporan.show', $l))->assertForbidden();

        $this->actingAs($koor)->post(route('laporan.verifikasi', $l), ['keputusan' => 'perbaikan'])->assertSessionHasErrors('catatan_verifikasi');
        $this->actingAs($koor)->post(route('laporan.verifikasi', $l), ['keputusan' => 'perbaikan', 'catatan_verifikasi' => 'Foto kurang'])->assertSessionHasNoErrors();
        // petugas memperbaiki → kembali menunggu
        $this->actingAs($this->petugas)->put(route('laporan.update', $l), $this->dataLaporan(['debit_intake_lps' => 99]))->assertRedirect();
        $this->assertSame('menunggu', $l->fresh()->status_verifikasi);

        $this->actingAs($koor)->post(route('laporan.verifikasi', $l), ['keputusan' => 'disetujui']);
        $this->assertSame('disetujui', $l->fresh()->status_verifikasi);
        $this->actingAs($this->petugas)->get(route('laporan.edit', $l))->assertForbidden();
        $this->actingAs($this->petugas)->delete(route('laporan.destroy', $l))->assertForbidden();
    }

    public function test_peta_publik_tanpa_data_pribadi(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan());
        auth()->logout();
        $json = $this->getJson('/api/publik/peta')->assertOk()->json();
        $this->assertSame('siaga', collect($json['daerah_irigasi'])->firstWhere('kode', 'DI-01')['status']);
        $this->assertStringNotContainsString('081200000001', json_encode($json));
        $this->assertStringNotContainsString('Petugas A', json_encode($json));
    }

    public function test_peta_internal_dibatasi_wilayah_petugas(): void
    {
        $json = $this->actingAs($this->petugas)->getJson('/api/peta')->assertOk()->json();
        $this->assertSame(['DI-01'], collect($json['daerah_irigasi'])->pluck('kode')->all());
    }

    public function test_halaman_utama_bisa_dibuka_tiap_role(): void
    {
        foreach ([Role::SUPER_ADMIN, Role::PIMPINAN, Role::ADMIN_OP, Role::PETUGAS] as $i => $role) {
            $u = $role === Role::PETUGAS ? $this->petugas : User::create(['name' => $role, 'username' => 'u'.$i, 'password' => 'x', 'role' => $role, 'must_change_password' => false]);
            foreach (['/dashboard', '/peta', '/laporan', '/kejadian', '/master/daerah-irigasi', '/master/daerah-irigasi/'.$this->subA->daerah_irigasi_id] as $url) {
                $this->actingAs($u)->get($url)->assertOk();
            }
        }
        $this->actingAs($this->petugas)->get('/pengguna')->assertForbidden();
        $this->actingAs($this->petugas)->get('/master/daerah-irigasi/create')->assertForbidden();
    }

    public function test_pimpinan_tidak_bisa_mengisi_laporan(): void
    {
        $p = User::create(['name' => 'Kepala Balai', 'username' => 'kabalai', 'password' => 'x', 'role' => Role::PIMPINAN, 'must_change_password' => false]);
        $this->actingAs($p)->post('/laporan', $this->dataLaporan())->assertForbidden();
    }

    public function test_koordinat_bisa_diisi_manual_dan_harus_lengkap(): void
    {
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan(['lat' => -10.1, 'lng' => '']))
            ->assertSessionHasErrors('lng');
        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan(['lat' => -10.1234567, 'lng' => 130]))
            ->assertSessionHasErrors('lng');

        $this->actingAs($this->petugas)->post('/laporan', $this->dataLaporan(['lat' => '', 'lng' => '']))->assertSessionHasNoErrors();
        $l = LaporanHarian::firstOrFail();
        $this->assertNull($l->lat);

        $this->actingAs($this->petugas)->put(route('laporan.update', $l), $this->dataLaporan(['lat' => '-10.1458321', 'lng' => '123.8112345']))
            ->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(-10.1458321, $l->fresh()->lat, 1e-7);
    }

    public function test_petugas_melihat_siapa_verifikator_dan_manager_setujui_massal(): void
    {
        $diA = $this->subA->daerahIrigasi;
        $manager = User::create(['name' => 'Manager Oesao', 'username' => 'mgr', 'no_hp' => '081299990000', 'password' => 'x', 'role' => Role::MANAGER, 'must_change_password' => false, 'daerah_irigasi_id' => $diA->id]);
        $koor = User::create(['name' => 'Koordinator Timor', 'username' => 'koor', 'password' => 'x', 'role' => Role::KOORDINATOR, 'must_change_password' => false, 'wilayah_satgas_id' => $diA->kabupaten->wilayah_satgas_id]);

        $buat = fn ($sub, $tgl) => LaporanHarian::create(['tanggal' => $tgl, 'sub_daerah_irigasi_id' => $sub->id, 'user_id' => $this->petugas->id,
            'debit_intake_lps' => 10, 'tma_cm' => 10, 'kondisi_saluran' => 'normal']);
        $a1 = $buat($this->subA, today());
        $a2 = $buat($this->subA, today()->subDay());
        $b1 = $buat($this->subB, today());

        $this->assertSame(['Manager Oesao', 'Koordinator Timor'], $a1->calonVerifikator()->pluck('name')->all());
        $this->actingAs($this->petugas)->get(route('laporan.show', $a1))->assertOk()
            ->assertSee('Akan diverifikasi oleh salah satu')->assertSee('Manager Oesao')->assertDontSee('Simpan keputusan');

        $this->actingAs($this->petugas)->post(route('laporan.verifikasi-massal'), ['ids' => [$a1->id]])->assertForbidden();

        $this->actingAs($manager)->get(route('laporan.index'))->assertOk()->assertSee('menunggu verifikasi Anda')->assertSee('Setujui terpilih');
        $this->actingAs($manager)->post(route('laporan.verifikasi-massal'), ['ids' => [$a1->id, $a2->id, $b1->id]])
            ->assertSessionHas('ok', '2 laporan disetujui.');                                   // laporan D.I. lain dilewati
        $this->assertSame('disetujui', $a1->fresh()->status_verifikasi);
        $this->assertSame($manager->id, $a2->fresh()->diverifikasi_oleh);
        $this->assertSame('menunggu', $b1->fresh()->status_verifikasi);
    }
}
