<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\LaporanAir;
use App\Models\LaporanHarian;
use App\Models\PintuAir;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Models\WilayahSatgas;
use App\Services\CadanganService;
use App\Services\ImporPintuService;
use App\Support\Role;
use App\Support\Xlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Cadangan data, pengingat laporan Senin, dan impor data pintu dari Excel. */
class AlatOperasionalTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private SubDaerahIrigasi $sub;

    private SubDaerahIrigasi $sub2;

    private User $sa;

    private User $manager;

    private User $ppaA;

    private User $ppaB;

    protected function setUp(): void
    {
        parent::setUp();
        $w = WilayahSatgas::create(['kode' => 'II', 'nama' => 'Satgas II', 'cakupan' => 'Timor']);
        $kab = Kabupaten::create(['nama' => 'Kupang', 'wilayah_satgas_id' => $w->id]);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id]);
        $this->sub = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kiri', 'luas_ha' => 200]);
        $this->sub2 = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kanan', 'luas_ha' => 100]);
        $buat = fn ($role, $u, $x = []) => User::create(['name' => ucfirst($u), 'username' => $u, 'password' => 'x', 'role' => $role, 'must_change_password' => false] + $x);
        $this->sa = $buat(Role::SUPER_ADMIN, 'sa');
        $this->manager = $buat(Role::MANAGER, 'manager', ['daerah_irigasi_id' => $this->di->id, 'no_hp' => '081200000009']);
        $this->ppaA = $buat(Role::PETUGAS, 'ppaa', ['daerah_irigasi_id' => $this->di->id, 'no_hp' => '081200000001']);
        $this->ppaB = $buat(Role::PETUGAS, 'ppab', ['daerah_irigasi_id' => $this->di->id, 'no_hp' => '0812-0000-0002']);
        $this->ppaA->subDaerahIrigasi()->attach($this->sub);
        $this->ppaB->subDaerahIrigasi()->attach($this->sub2);
    }

    private string $folder = '/nonexistent-iris';

    protected function tearDown(): void
    {
        File::deleteDirectory($this->folder);
        parent::tearDown();
    }

    public function test_cadangan_database_dan_menu_super_admin(): void
    {
        // folder sementara agar cadangan sungguhan di storage/app/cadangan tidak tersentuh
        $this->folder = sys_get_temp_dir().'/iris-uji-cadangan-'.uniqid();
        config(['services.cadangan.folder' => $this->folder]);
        $this->artisan('iris:cadangan')->assertSuccessful();
        $f = app(CadanganService::class)->terakhir();
        $this->assertNotNull($f);
        $sql = gzdecode(file_get_contents($this->folder.'/'.$f['nama']));
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString("'Oesao Kiri'", $sql);
        $this->assertFileExists($this->folder.'/.htaccess');

        $this->actingAs($this->sa)->get(route('cadangan.index'))->assertOk()->assertSee($f['nama'])->assertSee('Cadangan otomatis berjalan');
        $this->actingAs($this->sa)->get(route('cadangan.unduh', $f['nama']))->assertOk()->assertDownload($f['nama']);
        $this->actingAs($this->sa)->get(route('cadangan.unduh', '..%2F.env'))->assertNotFound();
        $this->actingAs($this->sa)->get(route('cadangan.skrip'))->assertOk()->assertSee('X-Cadangan-Token', false)->assertSee(route('cadangan.ambil'), false);
        $this->actingAs($this->manager)->get(route('cadangan.index'))->assertForbidden();

        // unduhan untuk laptop/NAS: nonaktif tanpa token, harus cocok
        $this->get(route('cadangan.ambil'))->assertNotFound();
        config(['services.cadangan.token' => str_repeat('a', 40)]);
        $this->get(route('cadangan.ambil'), ['X-Cadangan-Token' => 'salah'])->assertNotFound();
        $this->get(route('cadangan.ambil'), ['X-Cadangan-Token' => str_repeat('a', 40)])->assertOk()->assertDownload($f['nama']);

        // retensi: hanya 14 terakhir
        foreach (range(1, 15) as $i) {
            File::put($this->folder.('/iris-db-20200101-0000'.str_pad($i, 2, '0', STR_PAD_LEFT).'.sql.gz'), 'x');
            touch($this->folder.('/iris-db-20200101-0000'.str_pad($i, 2, '0', STR_PAD_LEFT).'.sql.gz'), now()->subDays(30 - $i)->timestamp);
        }
        $this->actingAs($this->sa)->post(route('cadangan.store'), ['jenis' => 'db'])->assertSessionHas('ok');
        $this->assertCount(CadanganService::SIMPAN_DB, app(CadanganService::class)->daftar()->where('jenis', 'db'));

        $this->actingAs($this->sa)->post(route('cadangan.store'), ['jenis' => 'foto'])->assertSessionHas('ok');
        $this->assertNotNull(app(CadanganService::class)->terakhir('foto'));
    }

    public function test_pengingat_laporan_senin_dengan_whatsapp(): void
    {
        $t = PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $this->sub->id, 'kode' => 'T1', 'jenis' => 'tersier', 'tingkat' => 'tersier']);
        $t->petugas()->attach($this->ppaA, ['peran' => 'utama']);
        // PPA B sudah lapor pekan ini, PPA A dan Manager belum
        LaporanHarian::create(['tanggal' => today(), 'sub_daerah_irigasi_id' => $this->sub2->id, 'user_id' => $this->ppaB->id]);

        $res = $this->actingAs($this->manager)->get(route('pengingat'))->assertOk()
            ->assertSee('Ppaa')->assertSee('Petak tugas: T1')->assertSee('Belum melapor pekan ini')->assertDontSee('Ppab')
            ->assertSee('https://wa.me/6281200000001', false)->assertSee('Belum masuk pekan ini');
        $res->assertViewHas('ringkas', fn ($r) => $r['ppa_belum'] === 1 && $r['ppa_total'] === 2 && $r['air_belum'] === 1);
        $this->actingAs($this->manager)->get(route('pengingat', ['belum' => 0]))->assertOk()->assertSee('Ppab')->assertSee('Sudah masuk');

        LaporanAir::create(['tanggal' => today(), 'daerah_irigasi_id' => $this->di->id, 'user_id' => $this->manager->id, 'tma_sungai_cm' => 50]);
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee('Pengingat laporan pekan ini')->assertSee('1 dari 2 PPA');
        $this->actingAs($this->ppaA)->get(route('pengingat'))->assertForbidden();

        // pekan lalu: belum ada laporan sama sekali
        $this->actingAs($this->manager)->get(route('pengingat', ['pekan' => today()->subWeek()->toDateString()]))->assertOk()
            ->assertViewHas('ringkas', fn ($r) => $r['ppa_belum'] === 2 && $r['air_belum'] === 1);
    }

    public function test_impor_data_pintu_dari_excel(): void
    {
        $intake = PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'kode' => 'INTAKE', 'jenis' => 'intake', 'tingkat' => 'utama', 'urutan' => 1]);
        $sek = PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $this->sub->id, 'kode' => 'BO.1', 'jenis' => 'bagi', 'tingkat' => 'sekunder', 'hulu_id' => $intake->id, 'urutan' => 2]);

        $this->actingAs($this->manager)->get(route('master.pintu-air.impor', ['di' => $this->di->id]))->assertOk()->assertSee('Unduh template Excel');
        $res = $this->actingAs($this->manager)->get(route('master.pintu-air.impor.template', $this->di))->assertOk();
        $tpl = tempnam(sys_get_temp_dir(), 't').'.xlsx';
        copy($res->baseResponse->getFile()->getPathname(), $tpl);
        $isi = Xlsx::baca($tpl, 'Pintu');
        $this->assertSame('Kode pintu *', $isi[1][0]);
        $this->assertSame(['INTAKE', 'BO.1'], [$isi[2][0], $isi[3][0]]);

        // isi: ubah BO.1, tambah 2 petak tersier (hulu BO.1), satu dengan petugas
        $baris = $isi;
        $baris[3][6] = 150;
        $baris[3][7] = '120,5';
        $baris[4] = ['T1', 'Petak 1', 'tersier', 'tersier', 'Oesao Kiri', 'BO.1', 50, 25, 10, 40, 10, 60, null, 3, 'Ya', '6281200000001', '081200000002', null];
        $baris[5] = ['T2', null, 'tersier', 'tersier', 'oesao kiri', 'BO.1', 100, null, null, null, null, null, null, 4, 'Ya', null, null, null];
        $buatFile = function (array $baris) {
            $p = tempnam(sys_get_temp_dir(), 'u').'.xlsx';
            $judul = array_column(ImporPintuService::KOLOM, 0);
            Xlsx::tulis($p, [['nama' => 'Pintu', 'baris' => array_values(array_map(fn ($r) => array_replace(array_fill(0, count($judul), null), $r), $baris))]]);

            return new UploadedFile($p, 'isi.xlsx', null, null, true);
        };

        // galat: tingkat salah & hulu tersier
        $salah = $baris;
        $salah[6] = ['X9', null, 'kuarter', 'bagi', null, 'T1'];
        $salah[7] = ['T3', null, 'tersier', 'tersier', null, 'T1'];
        $this->actingAs($this->manager)->post(route('master.pintu-air.impor.periksa', $this->di), ['file' => $buatFile($salah)])->assertOk()
            ->assertSee('Tingkat harus salah satu')->assertSee('bukan tersier')->assertDontSee('Simpan 3 perubahan');

        $res = $this->actingAs($this->manager)->post(route('master.pintu-air.impor.periksa', $this->di), ['file' => $buatFile($baris)])->assertOk()
            ->assertSee('Simpan 3 perubahan')->assertSee('Ppaa');
        $token = $res->viewData('token');
        $this->assertSame(2, PintuAir::count()); // belum disimpan

        $this->actingAs($this->ppaA)->post(route('master.pintu-air.impor.terapkan', $this->di), ['token' => $token])->assertForbidden();
        $this->actingAs($this->manager)->post(route('master.pintu-air.impor.terapkan', $this->di), ['token' => $token])
            ->assertRedirect(route('pintu.index', ['di' => $this->di->id]))->assertSessionHas('ok', 'Impor selesai: 2 pintu baru, 1 pintu diubah.');
        $this->assertSame(120.5, (float) $sek->fresh()->debit_rencana_lps);
        $t1 = PintuAir::where('kode', 'T1')->firstOrFail();
        $this->assertSame([$sek->id, $this->sub->id, 50.0], [$t1->hulu_id, $t1->sub_daerah_irigasi_id, (float) $t1->luas_layanan_ha]);
        $this->assertEqualsCanonicalizing([$this->ppaA->id => 'utama', $this->ppaB->id => 'pengganti'], $t1->petugas->mapWithKeys(fn ($u) => [$u->id => $u->pivot->peran])->all());
        // token sekali pakai
        $this->actingAs($this->manager)->post(route('master.pintu-air.impor.terapkan', $this->di), ['token' => $token])->assertSessionHasErrors('file');
    }
}
