<?php

namespace Tests\Feature;

use App\Models\DaerahIrigasi;
use App\Models\Kabupaten;
use App\Models\PembacaanPintu;
use App\Models\PintuAir;
use App\Models\SesiPintu;
use App\Models\SkemaJaringan;
use App\Models\SubDaerahIrigasi;
use App\Models\User;
use App\Support\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PintuAirTest extends TestCase
{
    use RefreshDatabase;

    private DaerahIrigasi $di;

    private DaerahIrigasi $diLain;

    private SubDaerahIrigasi $subA;

    private SubDaerahIrigasi $subB;

    private User $manager;

    private User $petugas;

    private User $pimpinan;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('uploads');
        $kab = Kabupaten::create(['nama' => 'Kupang']);
        $this->di = DaerahIrigasi::create(['kode' => 'DI-01', 'nama' => 'Oesao', 'kabupaten_id' => $kab->id]);
        $this->diLain = DaerahIrigasi::create(['kode' => 'DI-02', 'nama' => 'Manikin', 'kabupaten_id' => $kab->id]);
        $this->subA = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kiri']);
        $this->subB = $this->di->subDaerahIrigasi()->create(['nama' => 'Oesao Kanan']);
        $this->diLain->subDaerahIrigasi()->create(['nama' => 'Manikin']);
        $buat = fn ($role, $u, $di = null) => User::create(['name' => ucfirst($u), 'username' => $u, 'no_hp' => '0812'.rand(1000000, 9999999), 'password' => 'x', 'role' => $role, 'must_change_password' => false, 'daerah_irigasi_id' => $di]);
        $this->manager = $buat(Role::MANAGER, 'manager', $this->di->id);
        $this->petugas = $buat(Role::PETUGAS, 'ppa', $this->di->id);
        $this->petugas->subDaerahIrigasi()->attach($this->subA);
        $this->pimpinan = $buat(Role::PIMPINAN, 'kasatker');
    }

    private function pintu(string $kode, ?SubDaerahIrigasi $sub, array $attr = []): PintuAir
    {
        return PintuAir::create(['daerah_irigasi_id' => $this->di->id, 'sub_daerah_irigasi_id' => $sub?->id, 'kode' => $kode, 'jenis' => 'sadap'] + $attr);
    }

    public function test_manager_menambah_banyak_pintu_dan_validasi(): void
    {
        $this->actingAs($this->manager)->get(route('master.pintu-air.create', ['di' => $this->di->id]))->assertOk();
        $this->actingAs($this->manager)->post(route('master.pintu-air.store'), [
            'daerah_irigasi_id' => $this->di->id,
            'pintu' => [
                ['kode' => ' bos.1 ki ', 'jenis' => 'sadap', 'sub_daerah_irigasi_id' => $this->subA->id, 'debit_min_lps' => 80, 'debit_maks_lps' => 120],
                ['kode' => 'BOS.2', 'jenis' => 'bagi', 'sub_daerah_irigasi_id' => $this->subB->id],
                ['kode' => '', 'jenis' => 'sadap'],                    // baris kosong diabaikan
                ['kode' => 'INTAKE', 'jenis' => 'intake', 'tma_min_cm' => 40],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect(route('pintu.index', ['di' => $this->di->id]));
        $this->assertSame(['bos.1 ki', 'BOS.2', 'INTAKE'], PintuAir::orderBy('urutan')->pluck('kode')->all());

        // kode ganda, batas atas < bawah, Sub-D.I. dari D.I. lain
        $this->actingAs($this->manager)->post(route('master.pintu-air.store'), [
            'daerah_irigasi_id' => $this->di->id,
            'pintu' => [
                ['kode' => 'BOS.2', 'jenis' => 'sadap'],
                ['kode' => 'BOS.3', 'jenis' => 'sadap', 'debit_min_lps' => 100, 'debit_maks_lps' => 50],
                ['kode' => 'BOS.4', 'jenis' => 'sadap', 'sub_daerah_irigasi_id' => $this->diLain->subDaerahIrigasi()->first()->id],
            ],
        ])->assertSessionHasErrors(['pintu.0.kode', 'pintu.1.debit_maks_lps', 'pintu.2.sub_daerah_irigasi_id']);

        // manager tidak bisa di D.I. lain; petugas tidak punya izin kelola pintu
        $this->actingAs($this->manager)->get(route('master.pintu-air.create', ['di' => $this->diLain->id]))->assertForbidden();
        $this->actingAs($this->petugas)->get(route('master.pintu-air.create', ['di' => $this->di->id]))->assertForbidden();
    }

    public function test_petugas_membaca_pintu_di_wilayahnya_dan_status_dihitung(): void
    {
        $a = $this->pintu('BOS.1', $this->subA, ['debit_min_lps' => 80, 'debit_maks_lps' => 120, 'tma_min_cm' => 30, 'tma_maks_cm' => 60]);
        $intake = $this->pintu('INTAKE', null, ['debit_min_lps' => 500]);
        $b = $this->pintu('BOS.2', $this->subB);

        $this->actingAs($this->petugas)->get(route('pembacaan-pintu.create', ['di' => $this->di->id]))->assertOk()
            ->assertSee('BOS.1')->assertSee('INTAKE')->assertDontSee('BOS.2');

        // pintu Sub-D.I. lain ditolak
        $this->actingAs($this->petugas)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'rutin',
            'baca' => [$b->id => ['debit' => 10]],
        ])->assertForbidden();
        // kosong semua → galat
        $this->actingAs($this->petugas)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'rutin', 'baca' => [$a->id => ['debit' => '']],
        ])->assertSessionHasErrors('baca');

        // pembacaan Senin lalu, lalu pembacaan khusus panen hari ini
        $this->actingAs($this->petugas)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->subDays(7)->toDateString(), 'jenis' => 'rutin',
            'baca' => [$a->id => ['debit' => 100, 'tma' => 45]],
        ])->assertSessionHasNoErrors();
        $this->actingAs($this->petugas)->post(route('pembacaan-pintu.store'), [
            'daerah_irigasi_id' => $this->di->id, 'tanggal' => today()->toDateString(), 'jenis' => 'panen', 'jam' => '07:30',
            'baca' => [$a->id => ['debit' => 60, 'tma' => 25, 'catatan' => 'sampah di pintu'], $intake->id => ['debit' => 650]],
            'foto' => [UploadedFile::fake()->image('p.jpg', 800, 600)],
        ])->assertSessionHasNoErrors();

        $sesi = SesiPintu::latest('id')->first();
        $this->assertSame(2, $sesi->pembacaan()->count());
        $this->assertSame(1, $sesi->foto()->count());
        $this->assertSame('kurang', $a->statusDebit(60));
        $this->assertSame('rendah', $a->statusTma(25));
        $this->assertNull($b->statusDebit(60));                                 // rentang belum diisi

        $this->actingAs($this->petugas)->get(route('pembacaan-pintu.show', $sesi))->assertOk()
            ->assertSee('Kurang')->assertSee('40')->assertSee('(−40%)', false);
        $this->actingAs($this->manager)->get(route('pintu.index', ['di' => $this->di->id]))->assertOk()
            ->assertSee('BOS.1')->assertSee('Kurang')->assertSee('Rendah')->assertSee('Berlebih');   // intake 650 > 500
        $this->actingAs($this->manager)->get(route('pintu.index', ['di' => $this->di->id, 'saring' => 'belum']))->assertOk()
            ->assertSee('BOS.2')->assertDontSee('>BOS.1<', false);
        $this->actingAs($this->manager)->get(route('pintu.show', $a))->assertOk()->assertSee('Riwayat pembacaan');
        $this->actingAs($this->manager)->get(route('pintu.index'))->assertOk()->assertSee('Oesao');
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk()->assertSee('Pintu air minggu ini');
        $this->actingAs($this->petugas)->get(route('dashboard'))->assertOk()->assertSee('Pembacaan pintu air');
    }

    public function test_ubah_pembacaan_dibatasi_waktu_dan_pimpinan_tidak_mengisi(): void
    {
        $a = $this->pintu('BOS.1', $this->subA);
        $sesi = SesiPintu::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->petugas->id, 'tanggal' => today()->subDays(10), 'jenis' => 'rutin']);
        PembacaanPintu::create(['sesi_pintu_id' => $sesi->id, 'pintu_air_id' => $a->id, 'tanggal' => $sesi->tanggal, 'debit_lps' => 50]);

        $this->actingAs($this->petugas)->get(route('pembacaan-pintu.edit', $sesi))->assertForbidden();   // lewat batas ubah
        $this->actingAs($this->manager)->put(route('pembacaan-pintu.update', $sesi), [
            'tanggal' => $sesi->tanggal->toDateString(), 'jenis' => 'rutin', 'baca' => [$a->id => ['debit' => 55, 'tma' => 33]],
        ])->assertSessionHasNoErrors();
        $this->assertSame(55.0, PembacaanPintu::first()->debit_lps);

        $this->actingAs($this->pimpinan)->get(route('pembacaan-pintu.create'))->assertForbidden();
        $this->actingAs($this->pimpinan)->get(route('pintu.index', ['di' => $this->di->id]))->assertOk();
    }

    public function test_skema_dan_hapus_pintu_yang_sudah_dibaca(): void
    {
        $this->actingAs($this->manager)->post(route('master.skema.store', $this->di), [
            'judul' => 'Skema 2024', 'file' => UploadedFile::fake()->create('skema.pdf', 900, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $s = SkemaJaringan::firstOrFail();
        Storage::disk('uploads')->assertExists($s->path);
        $this->actingAs($this->petugas)->get(route('pintu.index', ['di' => $this->di->id]))->assertOk()->assertSee('Skema 2024');
        $this->actingAs($this->petugas)->delete(route('master.skema.destroy', $s))->assertForbidden();
        $this->actingAs($this->manager)->post(route('master.skema.store', $this->di), ['file' => UploadedFile::fake()->create('x.exe', 10)])
            ->assertSessionHasErrors('file');
        $this->actingAs($this->manager)->delete(route('master.skema.destroy', $s));
        Storage::disk('uploads')->assertMissing($s->path);

        $a = $this->pintu('BOS.1', $this->subA);
        $kosong = $this->pintu('BOS.9', $this->subA);
        $sesi = SesiPintu::create(['daerah_irigasi_id' => $this->di->id, 'user_id' => $this->petugas->id, 'tanggal' => today(), 'jenis' => 'rutin']);
        PembacaanPintu::create(['sesi_pintu_id' => $sesi->id, 'pintu_air_id' => $a->id, 'tanggal' => today(), 'debit_lps' => 50]);
        $this->actingAs($this->manager)->delete(route('master.pintu-air.destroy', $a));
        $this->actingAs($this->manager)->delete(route('master.pintu-air.destroy', $kosong));
        $this->assertFalse($a->fresh()->aktif);
        $this->assertNull($kosong->fresh());

        $this->actingAs($this->manager)->put(route('master.pintu-air.update', $a), ['kode' => 'BOS.1', 'jenis' => 'sadap', 'aktif' => 1, 'debit_min_lps' => 10])
            ->assertRedirect(route('pintu.show', $a));
        $this->assertTrue($a->fresh()->aktif);
    }
}
