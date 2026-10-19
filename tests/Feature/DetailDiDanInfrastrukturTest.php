<?php

namespace Tests\Feature;

use App\Models\Bangunan;
use App\Models\DaerahIrigasi;
use App\Models\Foto;
use App\Models\Kabupaten;
use App\Models\LaporanHarian;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Services\FotoService;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DetailDiDanInfrastrukturTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private DaerahIrigasi $diLain;

    private SubDaerahIrigasi $sub;

    private User $admin;

    private User $petugas;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Manikin', 'kabupaten_id' => $kab->id, 'lat' => -10.1, 'lng' => 123.7]);
        $this->diLain = DaerahIrigasi::create(['kode' => 'DI-02', 'nama' => 'Kambaniru', 'kabupaten_id' => $kab->id]);
        $this->sub = $this->di->subDaerahIrigasi()->create(['nama' => 'Air Sagu', 'luas_ha' => 97]);
        $this->diLain->subDaerahIrigasi()->create(['nama' => 'Kambaniru', 'luas_ha' => 100]);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'adm', 'password' => 'x', 'role' => Role::ADMIN_OP, 'must_change_password' => false]);
        $this->petugas = User::create(['name' => 'Petugas Sagu', 'username' => 'p1', 'no_hp' => '081234567890', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false, 'daerah_irigasi_id' => $this->di->id]);
        $this->petugas->subDaerahIrigasi()->attach($this->sub);
    }

    private function laporan(string $tanggal, string $verifikasi = 'disetujui', int $foto = 1): LaporanHarian
    {
        $l = LaporanHarian::create([
            'tanggal' => $tanggal, 'sub_daerah_irigasi_id' => $this->sub->id, 'user_id' => $this->petugas->id,
            'debit_intake_lps' => 77.7, 'tma_cm' => 30, 'kondisi_saluran' => 'kurang', 'luas_tanam_ha' => 50,
            'luas_kering_ha' => 10, 'status_verifikasi' => $verifikasi,
        ]);
        if ($foto) {
            app(FotoService::class)->simpan($l, array_map(fn ($i) => UploadedFile::fake()->image("f$i.jpg", 800, 600), range(1, $foto)), 'laporan');
        }

        return $l;
    }

    public function test_admin_menambah_infrastruktur_dengan_kondisi_dan_foto(): void
    {
        $this->actingAs($this->admin)->post(route('master.bangunan.store'), [
            'daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $this->sub->id,
            'nama' => 'Bendung Air Sagu', 'jenis' => 'bendung', 'kondisi' => 'rusak_ringan',
            'lat' => -10.11, 'lng' => 123.71, 'foto' => [UploadedFile::fake()->image('b.jpg', 1200, 900)],
        ])->assertSessionHasNoErrors();

        $b = Bangunan::firstOrFail();
        $this->assertSame('Rusak ringan', $b->kondisi_label);
        $this->assertSame(today()->toDateString(), $b->kondisi_per->toDateString());
        $this->assertSame(1, $b->foto()->count());

        $path = $b->foto()->first()->path;
        $this->actingAs($this->admin)->delete(route('master.bangunan.destroy', $b))->assertRedirect();
        $this->assertSame(0, Foto::count());
        Storage::disk('uploads')->assertMissing([$path, Foto::pathKecil($path)]);
    }

    public function test_sub_di_harus_milik_di_yang_dipilih_dan_petugas_tidak_boleh_mengelola(): void
    {
        $subLain = $this->diLain->subDaerahIrigasi()->first();
        $this->actingAs($this->admin)->post(route('master.bangunan.store'), [
            'daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $subLain->id, 'nama' => 'X', 'jenis' => 'bendung',
        ])->assertSessionHasErrors('sub_daerah_irigasi_id');

        $this->actingAs($this->petugas)->get(route('master.bangunan.create'))->assertForbidden();
        $this->actingAs($this->petugas)->get(route('master.bangunan.index'))->assertForbidden();   // menu data master tidak termasuk izin bawaan petugas
    }

    public function test_detail_publik_mengelompokkan_foto_per_tanggal_tanpa_data_internal(): void
    {
        $this->laporan(today()->toDateString(), 'menunggu', 2);
        $this->laporan(today()->subDay()->toDateString(), 'perbaikan');
        $this->laporan(today()->subDays(2)->toDateString());
        Bangunan::create(['daerah_irigasi_id' => $this->di->id, 'nama' => 'Bendung Manikin', 'jenis' => 'bendung', 'kondisi' => 'baik', 'lat' => -10.1, 'lng' => 123.7]);

        $r = $this->getJson(route('api.publik.di', $this->di))->assertOk();
        $r->assertJsonPath('di.nama', 'Manikin')
            ->assertJsonCount(2, 'foto_harian')                              // laporan "perbaikan" tidak tampil di publik
            ->assertJsonPath('foto_harian.0.tanggal', today()->toDateString())
            ->assertJsonCount(2, 'foto_harian.0.laporan.0.foto')
            ->assertJsonPath('foto_harian.0.laporan.0.terverifikasi', false)
            ->assertJsonPath('bangunan.0.kondisi_label', 'Baik')
            ->assertJsonCount(30, 'riwayat');
        $json = $r->getContent();
        $this->assertStringNotContainsString('081234567890', $json);
        $this->assertStringNotContainsString('Petugas Sagu', $json);
        $this->assertStringNotContainsString('77.7', $json);                 // debit tidak dibuka ke publik
        $this->assertNull($r->json('foto_harian.0.laporan.0.url'));
    }

    public function test_foto_lebih_lama_dimuat_per_30_hari(): void
    {
        $this->laporan(today()->toDateString());
        $this->laporan(today()->subDays(45)->toDateString());

        $awal = $this->getJson(route('api.publik.di', $this->di))->assertJsonCount(1, 'foto_harian');
        $sebelum = $awal->json('foto_sebelum');
        $this->assertSame(today()->subDays(45)->toDateString(), $sebelum);

        $this->getJson(route('api.publik.di', $this->di).'?sebelum='.$sebelum)
            ->assertJsonCount(1, 'foto_harian')->assertJsonPath('foto_sebelum', null);
    }

    public function test_detail_internal_menampilkan_petugas_dan_dibatasi_wilayah(): void
    {
        $this->laporan(today()->toDateString());

        $this->actingAs($this->petugas)->getJson(route('api.di', $this->di))->assertOk()
            ->assertJsonPath('sub.0.petugas.0.nama', 'Petugas Sagu')
            ->assertJsonPath('foto_harian.0.laporan.0.debit', 77.7);
        $this->actingAs($this->petugas)->getJson(route('api.di', $this->diLain))->assertForbidden();
        $this->actingAs($this->admin)->getJson(route('api.di', $this->diLain))->assertOk();
    }

    public function test_impor_siga_mengisi_kosong_tanpa_menimpa_isian_aplikasi(): void
    {
        $oesao = DaerahIrigasi::create(['kode' => 'DI-03', 'nama' => 'Oesao']);
        $lama = Bangunan::create(['daerah_irigasi_id' => $oesao->id, 'nama' => 'Bendung Oesao', 'jenis' => 'bendung', 'kondisi' => 'rusak_berat', 'kondisi_per' => '2026-10-01']);
        Bangunan::create(['daerah_irigasi_id' => $oesao->id, 'nama' => 'Bendung Dalam Kom', 'jenis' => 'bendung']);

        $this->seed(\Database\Seeders\InfrastrukturSigaSeeder::class);
        $this->seed(\Database\Seeders\InfrastrukturSigaSeeder::class); // dijalankan ulang: tidak ganda

        $lama->refresh();
        $this->assertNotNull($lama->lat);
        $this->assertSame('rusak_berat', $lama->kondisi);                  // isian dari aplikasi tetap
        $this->assertSame('Tetap', $lama->data_teknis['Tipe bendung']);
        $this->assertTrue(Bangunan::where('nama', 'Bendung Dalam Kom 2')->exists());   // diberi nomor
        $this->assertSame(1, Bangunan::where('nama', 'Bendung Pukdale')->count());     // baru, tidak ganda
        $this->assertSame('baik', Bangunan::where('nama', 'Bendung Pukdale')->value('kondisi'));

        $this->getJson(route('api.publik.di', $oesao))->assertOk()->assertJsonPath('bangunan.0.teknis.0.label', fn ($v) => is_string($v));
    }

    public function test_foto_profil_di_maksimal_3_dan_tampil_di_peta_publik(): void
    {
        $gambar = fn ($n) => array_map(fn ($i) => UploadedFile::fake()->image("p$i.jpg", 1200, 800), range(1, $n));

        $this->actingAs($this->petugas)->post(route('master.daerah-irigasi.foto.store', $this->di), ['foto' => $gambar(1)])->assertForbidden();

        $this->actingAs($this->admin)->post(route('master.daerah-irigasi.foto.store', $this->di), [
            'foto' => $gambar(2), 'judul' => 'Walkthrough 2025', 'diambil_pada' => '2025-08-12',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok', '2 foto profil ditambahkan.');
        $this->actingAs($this->admin)->post(route('master.daerah-irigasi.foto.store', $this->di), ['foto' => $gambar(2)])
            ->assertSessionHasErrors('foto');                                     // hanya sisa 1 tempat

        [$a, $b] = $this->di->fotoProfil()->get()->all();
        $this->actingAs($this->admin)->patch(route('master.daerah-irigasi.foto.update', [$this->di, $b]), ['judul' => 'ePAKSI saluran induk', 'utama' => 1])
            ->assertSessionHasNoErrors();

        $r = $this->getJson(route('api.publik.di', $this->di))->assertOk();
        $r->assertJsonCount(2, 'profil')
            ->assertJsonPath('profil.0.judul', 'ePAKSI saluran induk')              // jadi foto utama
            ->assertJsonPath('profil.1.tanggal', '12 Agt 2025');

        $path = $a->path;
        $this->actingAs($this->admin)->delete(route('master.daerah-irigasi.foto.destroy', [$this->di, $a]))->assertRedirect();
        Storage::disk('uploads')->assertMissing($path);
        $this->actingAs($this->admin)->get(route('master.daerah-irigasi.show', $this->di))->assertOk()->assertSee('Foto profil D.I.')->assertSee('1/3');
    }
}
