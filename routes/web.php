<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HakAksesController;
use App\Http\Controllers\KejadianController;
use App\Http\Controllers\LaporanHarianController;
use App\Http\Controllers\Master\BangunanController;
use App\Http\Controllers\Master\DaerahIrigasiController;
use App\Http\Controllers\Master\JadwalTanamController;
use App\Http\Controllers\Master\P3aController;
use App\Http\Controllers\Master\PetaDiController;
use App\Http\Controllers\Master\SubDaerahIrigasiController;
use App\Http\Controllers\Master\PintuAirController as MasterPintuAirController;
use App\Http\Controllers\PembacaanPintuController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\PetugasController;
use App\Http\Controllers\PintuAirController;
use App\Http\Controllers\RekapController;
use Illuminate\Support\Facades\Route;

// ---------- Publik (tanpa login) ----------
Route::get('/', [PetaController::class, 'publik'])->name('beranda');
Route::get('/api/publik/peta', [PetaController::class, 'dataPublik'])->name('api.publik.peta')->middleware('throttle:60,1');
Route::get('/api/publik/di/{di}', [PetaController::class, 'detailPublik'])->name('api.publik.di')->middleware('throttle:120,1');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:10,1');
});

// ---------- Internal ----------
Route::middleware(['auth', 'siap'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/akun/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/akun/password', [AuthController::class, 'updatePassword'])->name('password.update');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/rekap/unduh', [RekapController::class, 'unduh'])->name('rekap.unduh')->middleware('can:unduh-rekap');

    Route::get('/peta', [PetaController::class, 'internal'])->name('peta');
    Route::get('/api/peta', [PetaController::class, 'dataInternal'])->name('api.peta');
    Route::get('/api/di/{di}', [PetaController::class, 'detailInternal'])->name('api.di');

    Route::get('/laporan/ekspor', [LaporanHarianController::class, 'ekspor'])->name('laporan.ekspor')->middleware('can:unduh-rekap');
    Route::post('/laporan/verifikasi-massal', [LaporanHarianController::class, 'verifikasiMassal'])->name('laporan.verifikasi-massal');
    Route::post('/laporan/{laporan}/verifikasi', [LaporanHarianController::class, 'verifikasi'])->name('laporan.verifikasi');
    Route::delete('/laporan/{laporan}/foto/{foto}', [LaporanHarianController::class, 'hapusFoto'])->name('laporan.foto.destroy');
    Route::resource('laporan', LaporanHarianController::class)->parameters(['laporan' => 'laporan']);

    Route::patch('/kejadian/{kejadian}/penanganan', [KejadianController::class, 'penanganan'])->name('kejadian.penanganan');
    Route::post('/kejadian/{kejadian}/foto', [KejadianController::class, 'tambahFoto'])->name('kejadian.foto.store');
    Route::delete('/kejadian/{kejadian}/foto/{foto}', [KejadianController::class, 'hapusFoto'])->name('kejadian.foto.destroy');
    Route::resource('kejadian', KejadianController::class)->parameters(['kejadian' => 'kejadian']);

    Route::get('/pintu-air', [PintuAirController::class, 'index'])->name('pintu.index');
    Route::get('/pintu-air/{pintu}', [PintuAirController::class, 'show'])->name('pintu.show')->whereNumber('pintu');
    Route::delete('/pembacaan-pintu/{sesi}/foto/{foto}', [PembacaanPintuController::class, 'hapusFoto'])->name('pembacaan-pintu.foto.destroy');
    Route::resource('pembacaan-pintu', PembacaanPintuController::class)->parameters(['pembacaan-pintu' => 'sesi']);

    Route::post('/petugas/{petugas}/reset-password', [PetugasController::class, 'resetPassword'])->name('petugas.reset');
    Route::resource('petugas', PetugasController::class)->parameters(['petugas' => 'petugas'])->except(['show']);

    // master data: semua user internal boleh melihat, hanya pengelola yang mengubah (dicek di controller)
    Route::prefix('master')->name('master.')->group(function () {
        Route::post('daerah-irigasi/{di}/foto-profil', [DaerahIrigasiController::class, 'fotoProfilStore'])->name('daerah-irigasi.foto.store');
        Route::patch('daerah-irigasi/{di}/foto-profil/{foto}', [DaerahIrigasiController::class, 'fotoProfilUpdate'])->name('daerah-irigasi.foto.update');
        Route::delete('daerah-irigasi/{di}/foto-profil/{foto}', [DaerahIrigasiController::class, 'fotoProfilDestroy'])->name('daerah-irigasi.foto.destroy');
        Route::resource('daerah-irigasi', DaerahIrigasiController::class)->parameters(['daerah-irigasi' => 'di']);
        Route::resource('daerah-irigasi.sub', SubDaerahIrigasiController::class)
            ->parameters(['daerah-irigasi' => 'di', 'sub' => 'sub'])->except(['index', 'show'])->shallow();
        Route::post('daerah-irigasi/{di}/skema', [MasterPintuAirController::class, 'skemaStore'])->name('skema.store');
        Route::delete('skema/{skema}', [MasterPintuAirController::class, 'skemaDestroy'])->name('skema.destroy');
        Route::resource('pintu-air', MasterPintuAirController::class)->parameters(['pintu-air' => 'pintu'])->except(['index', 'show']);
        Route::resource('jadwal-tanam', JadwalTanamController::class)->parameters(['jadwal-tanam' => 'jadwal'])->except(['show']);
        Route::resource('p3a', P3aController::class)->except(['show']);
        Route::delete('bangunan/{bangunan}/foto/{foto}', [BangunanController::class, 'hapusFoto'])->name('bangunan.foto.destroy');
        Route::resource('bangunan', BangunanController::class)->parameters(['bangunan' => 'bangunan'])->except(['show']);
        Route::get('peta/import', [PetaDiController::class, 'importForm'])->name('peta.import');
        Route::post('peta/import', [PetaDiController::class, 'import'])->name('peta.import.store');
        Route::get('peta/unduh', [PetaDiController::class, 'unduh'])->name('peta.unduh');
        Route::post('peta/bangun-ulang', [PetaDiController::class, 'bangunUlang'])->name('peta.bangun-ulang');
        Route::resource('peta', PetaDiController::class)->parameters(['peta' => 'peta'])->except(['show']);
    });

    Route::middleware('can:kelola-pengguna')->group(function () {
        Route::post('/pengguna/{pengguna}/reset-password', [PenggunaController::class, 'resetPassword'])->name('pengguna.reset');
        Route::resource('pengguna', PenggunaController::class)->parameters(['pengguna' => 'pengguna'])->except(['show']);
    });

    Route::middleware('can:kelola-hak-akses')->prefix('hak-akses')->name('hak-akses.')->group(function () {
        Route::get('/', [HakAksesController::class, 'index'])->name('index');
        Route::put('/', [HakAksesController::class, 'update'])->name('update');
        Route::post('/bawaan', [HakAksesController::class, 'bawaan'])->name('bawaan');
        Route::post('/pengecualian', [HakAksesController::class, 'simpanPengecualian'])->name('pengecualian.store');
        Route::delete('/pengecualian/{pengecualian}', [HakAksesController::class, 'hapusPengecualian'])->name('pengecualian.destroy');
    });

    Route::get('/audit', AuditController::class)->name('audit')->middleware('can:lihat-audit');
});
