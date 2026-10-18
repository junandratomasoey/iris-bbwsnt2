<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\PetaDi;
use App\Models\User;
use App\Services\PetaDiService;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PetaDiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private DaerahIrigasi $oesao;

    private string $cadangan;

    protected function setUp(): void
    {
        parent::setUp();
        // lindungi file geojson asli selama tes
        $this->cadangan = sys_get_temp_dir().'/iris-geojson-'.uniqid();
        File::copyDirectory(public_path('geojson'), $this->cadangan);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'admin', 'password' => 'x', 'role' => Role::ADMIN_OP, 'must_change_password' => false]);
        $this->oesao = DaerahIrigasi::create(['kode' => 'DI-03', 'nama' => 'Oesao', 'alias' => ['D.I OESAO']]);
        DaerahIrigasi::create(['kode' => 'DI-17', 'nama' => "So'a / Ngada", 'alias' => ['D.I SOA / NGADA']]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(public_path('geojson'));
        File::copyDirectory($this->cadangan, public_path('geojson'));
        File::deleteDirectory($this->cadangan);
        parent::tearDown();
    }

    /** Persegi ±1 km x 1 km di sekitar Oesao (lng, lat). */
    private function kotak(float $lng = 123.83, float $lat = -10.12, float $d = 0.009): array
    {
        return [[[$lng, $lat], [$lng + $d, $lat], [$lng + $d, $lat + $d], [$lng, $lat + $d], [$lng, $lat]]];
    }

    private function geojson(array $features): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('batas.geojson', json_encode(['type' => 'FeatureCollection', 'features' => $features]));
    }

    public function test_luas_geodesik_mendekati_luas_sebenarnya(): void
    {
        $ha = app(PetaDiService::class)->luasHa(['type' => 'Polygon', 'coordinates' => $this->kotak()]);
        // 0.009° ≈ 1001 m (bujur, dikoreksi cos 10°) x 995 m (lintang) ≈ 97-100 Ha
        $this->assertGreaterThan(95, $ha);
        $this->assertLessThan(101, $ha);
    }

    public function test_import_menggabungkan_potongan_dan_mencocokkan_nama(): void
    {
        $this->actingAs($this->admin)->post(route('master.peta.import.store'), [
            'lapisan' => 'fungsional', 'mode' => 'ganti_di',
            'berkas' => $this->geojson([
                ['type' => 'Feature', 'properties' => ['Nm_Inf' => 'D.I. Oesao'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $this->kotak()]],
                ['type' => 'Feature', 'properties' => ['Nm_Inf' => 'D.I. Oesao'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $this->kotak(123.85)]],
                ['type' => 'Feature', 'properties' => ['Nm_Inf' => "D.I. So'a"], 'geometry' => ['type' => 'Polygon', 'coordinates' => $this->kotak(121.0, -8.8)]],
                ['type' => 'Feature', 'properties' => ['Nm_Inf' => 'D.I. Pota'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $this->kotak(120.8, -8.4)]],
            ]),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $oesao = PetaDi::where('daerah_irigasi_id', $this->oesao->id)->sole();
        $this->assertSame('MultiPolygon', $oesao->geometri['type']);
        $this->assertSame(2, $oesao->jumlahBagian());
        $this->assertEqualsWithDelta(196, $this->oesao->fresh()->luas_fungsional_ha, 6);
        $this->assertNotNull($this->oesao->fresh()->lat);
        $this->assertSame(1, PetaDi::whereNull('daerah_irigasi_id')->count(), 'Pota belum terhubung');
        $this->assertSame(1, PetaDi::whereHas('daerahIrigasi', fn ($q) => $q->where('kode', 'DI-17'))->count(), "So'a cocok ke So'a / Ngada");

        $file = json_decode(File::get(public_path('geojson/fungsional.geojson')), true);
        $this->assertCount(3, $file['features']);
        $this->assertContains('DI-03', array_column(array_column($file['features'], 'properties'), 'kode'));

        // import ulang per D.I. mengganti, bukan menumpuk
        $this->actingAs($this->admin)->post(route('master.peta.import.store'), [
            'lapisan' => 'fungsional', 'mode' => 'ganti_di',
            'berkas' => $this->geojson([['type' => 'Feature', 'properties' => ['nama' => 'OESAO'], 'geometry' => ['type' => 'Polygon', 'coordinates' => $this->kotak()]]]),
        ]);
        $this->assertSame(1, PetaDi::where('daerah_irigasi_id', $this->oesao->id)->count());
        $this->assertEqualsWithDelta(98, $this->oesao->fresh()->luas_fungsional_ha, 3);
    }

    public function test_koordinat_proyeksi_meter_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('master.peta.import.store'), [
            'lapisan' => 'baku', 'mode' => 'tambah',
            'berkas' => $this->geojson([['type' => 'Feature', 'properties' => ['nama' => 'Oesao'], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[13780000, -1120000], [13781000, -1120000], [13781000, -1119000], [13780000, -1120000]]]]]]),
        ])->assertSessionHasErrors('berkas');
        $this->assertSame(0, PetaDi::count());
    }

    public function test_gambar_dan_ubah_batas_dari_editor(): void
    {
        $this->actingAs($this->admin)->post(route('master.peta.store'), [
            'lapisan' => 'baku', 'daerah_irigasi_id' => $this->oesao->id, 'nama' => 'D.I. Oesao',
            'geometri' => json_encode(['type' => 'Polygon', 'coordinates' => $this->kotak()]),
        ])->assertSessionHasNoErrors();
        $p = PetaDi::sole();
        $this->assertEqualsWithDelta(98, $p->luas_ha, 3);

        $this->actingAs($this->admin)->put(route('master.peta.update', $p), [
            'lapisan' => 'baku', 'daerah_irigasi_id' => $this->oesao->id, 'nama' => 'D.I. Oesao',
            'geometri' => json_encode(['type' => 'Polygon', 'coordinates' => $this->kotak(123.83, -10.12, 0.018)]),
        ])->assertSessionHasNoErrors();
        $this->assertEqualsWithDelta(392, $this->oesao->fresh()->luas_baku_ha, 10);

        $this->actingAs($this->admin)->delete(route('master.peta.destroy', $p));
        $this->assertNull($this->oesao->fresh()->luas_baku_ha);
        $this->assertCount(0, json_decode(File::get(public_path('geojson/baku.geojson')), true)['features']);
    }

    public function test_petugas_hanya_bisa_melihat(): void
    {
        $ptg = User::create(['name' => 'PPA', 'username' => 'ppa', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false]);
        $this->actingAs($ptg)->get(route('master.peta.index'))->assertOk();
        $this->actingAs($ptg)->get(route('master.peta.create'))->assertForbidden();
        $this->actingAs($ptg)->post(route('master.peta.store'), [])->assertForbidden();
    }

    public function test_halaman_editor_dan_import_terbuka(): void
    {
        $this->actingAs($this->admin)->get(route('master.peta.create', ['di' => $this->oesao->id]))->assertOk()->assertSee('D.I. Oesao');
        $this->actingAs($this->admin)->get(route('master.peta.import'))->assertOk();
        $this->actingAs($this->admin)->get(route('master.peta.unduh', ['lapisan' => 'baku']))->assertOk();
    }

    public function test_file_batas_wilayah_ntt_valid(): void
    {
        $data = json_decode(file_get_contents(public_path('geojson/wilayah-ntt.geojson')), true);
        $this->assertSame('FeatureCollection', $data['type']);
        $this->assertCount(21, $data['features']);
        $nama = collect($data['features'])->pluck('properties.nama');
        $this->assertTrue($nama->contains('Kabupaten Kupang') && $nama->contains('Kota Kupang'));
        // koordinat WGS84 di sekitar NTT (bukan UTM meter)
        $titik = $data['features'][0]['geometry']['coordinates'][0][0][0];
        $this->assertTrue($titik[0] > 118 && $titik[0] < 126 && $titik[1] > -12 && $titik[1] < -7);
    }
}
