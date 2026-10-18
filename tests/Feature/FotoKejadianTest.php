<?php

namespace Tests\Feature;

use App\Models\Foto;
use App\Models\Kabupaten;
use App\Models\Kejadian;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoKejadianTest extends TestCase
{
    use RefreshDatabase;

    private User $pelapor;

    private User $admin;

    private Kejadian $kejadian;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->pelapor = User::create(['name' => 'PPA', 'username' => 'ppa1', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false]);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'adm', 'password' => 'x', 'role' => Role::ADMIN_OP, 'must_change_password' => false]);
        $this->kejadian = Kejadian::create(['tanggal_lapor' => today(), 'kabupaten_id' => $kab->id, 'user_id' => $this->pelapor->id, 'jenis_kegagalan' => 'Gagal Tanam',
            'luas_terdampak_ha' => 10, 'lat' => -10.1, 'lng' => 123.8, 'desa' => 'Tanah Putih', 'pelapor_hp' => '081234567890', 'pelapor_nama' => 'Pak Lois']);
    }

    public function test_pelapor_menambah_foto_dengan_thumbnail_dan_label(): void
    {
        $this->actingAs($this->pelapor)->post(route('kejadian.foto.store', $this->kejadian), [
            'jenis_foto' => 'sebelum',
            'foto' => [UploadedFile::fake()->image('a.jpg', 3000, 2000), UploadedFile::fake()->image('b.jpg', 800, 600)],
        ])->assertSessionHasNoErrors()->assertSessionHas('ok', '2 foto ditambahkan.');

        $f = $this->kejadian->foto()->first();
        $this->assertSame('sebelum', $f->keterangan);
        $this->assertSame('Sebelum penanganan', $f->label);
        Storage::disk('uploads')->assertExists([$f->path, Foto::pathKecil($f->path)]);
        [$w] = getimagesizefromstring(Storage::disk('uploads')->get(Foto::pathKecil($f->path)));
        $this->assertLessThanOrEqual(480, $w);
        $this->assertStringEndsWith('-kecil.jpg', $f->url_kecil);
    }

    public function test_admin_bisa_hapus_foto_dan_file_ikut_terhapus(): void
    {
        $this->actingAs($this->pelapor)->post(route('kejadian.foto.store', $this->kejadian), ['jenis_foto' => 'sesudah', 'foto' => [UploadedFile::fake()->image('a.jpg')]]);
        $f = $this->kejadian->foto()->first();
        $this->actingAs($this->admin)->delete(route('kejadian.foto.destroy', [$this->kejadian, $f]))->assertRedirect();
        $this->assertSame(0, $this->kejadian->foto()->count());
        Storage::disk('uploads')->assertMissing([$f->path, Foto::pathKecil($f->path)]);
    }

    public function test_petugas_lain_tidak_bisa_menambah_foto(): void
    {
        $lain = User::create(['name' => 'Lain', 'username' => 'lain', 'password' => 'x', 'role' => Role::PETUGAS, 'must_change_password' => false]);
        $this->actingAs($lain)->post(route('kejadian.foto.store', $this->kejadian), ['jenis_foto' => 'sebelum', 'foto' => [UploadedFile::fake()->image('a.jpg')]])->assertForbidden();
        $this->actingAs($lain)->get(route('kejadian.show', $this->kejadian))->assertOk()->assertDontSee('Tambah foto');
    }

    public function test_kuota_foto_dibatasi(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->kejadian->foto()->create(['path' => "x/$i.jpg"]);
        }
        $this->actingAs($this->admin)->post(route('kejadian.foto.store', $this->kejadian), ['jenis_foto' => 'lainnya', 'foto' => [UploadedFile::fake()->image('a.jpg')]])
            ->assertSessionHasErrors('foto');
    }

    public function test_foto_tampil_di_peta_publik_tanpa_data_pelapor(): void
    {
        $this->actingAs($this->pelapor)->post(route('kejadian.foto.store', $this->kejadian), ['jenis_foto' => 'sebelum', 'foto' => [UploadedFile::fake()->image('a.jpg')]]);
        auth()->logout();
        $k = collect($this->getJson('/api/publik/peta')->assertOk()->json('kejadian'))->first();
        $this->assertCount(1, $k['foto']);
        $this->assertSame('Sebelum penanganan', $k['foto'][0]['label']);
        $this->assertArrayHasKey('kecil', $k['foto'][0]);
        $this->assertArrayNotHasKey('pelapor', $k);
        $this->assertStringNotContainsString('081234567890', json_encode($k));
    }

    public function test_halaman_detail_dan_ubah_kejadian_terbuka(): void
    {
        $this->actingAs($this->pelapor)->post(route('kejadian.foto.store', $this->kejadian), ['jenis_foto' => 'sebelum', 'foto' => [UploadedFile::fake()->image('a.jpg')]]);
        $this->actingAs($this->pelapor)->get(route('kejadian.show', $this->kejadian))->assertOk()->assertSee('Sebelum penanganan')->assertSee('Tambah foto');
        $this->actingAs($this->pelapor)->get(route('kejadian.edit', $this->kejadian))->assertOk()->assertSee('Foto dokumentasi');
        $this->actingAs($this->admin)->get(route('kejadian.index'))->assertOk()->assertSee('1 foto');
    }
}
