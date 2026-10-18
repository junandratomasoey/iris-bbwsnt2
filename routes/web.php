<?php

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KejadianController;
use App\Http\Controllers\LaporanHarianController;
use App\Http\Controllers\Master\DaerahIrigasiController;
use App\Http\Controllers\Master\JadwalTanamController;
use App\Http\Controllers\Master\P3aController;
use App\Http\Controllers\Master\PetaDiController;
use App\Http\Controllers\Master\SubDaerahIrigasiController;
use App\Http\Controllers\PenggunaController;
use App\Http\Controllers\PetaController;
use App\Http\Controllers\PetugasController;
use Illuminate\Support\Facades\Route;

// ---------- Publik (tanpa login) ----------
Route::get('/', [PetaController::class, 'publik'])->name('beranda');
Route::get('/api/publik/peta', [PetaController::class, 'dataPublik'])->name('api.publik.peta')->middleware('throttle:60,1');

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

    Route::get('/peta', [PetaController::class, 'internal'])->name('peta');
    Route::get('/api/peta', [PetaController::class, 'dataInternal'])->name('api.peta');

    Route::get('/laporan/ekspor', [LaporanHarianController::class, 'ekspor'])->name('laporan.ekspor');
    Route::post('/laporan/{laporan}/verifikasi', [LaporanHarianController::class, 'verifikasi'])->name('laporan.verifikasi');
    Route::delete('/laporan/{laporan}/foto/{foto}', [LaporanHarianController::class, 'hapusFoto'])->name('laporan.foto.destroy');
    Route::resource('laporan', LaporanHarianController::class)->parameters(['laporan' => 'laporan']);

    Route::patch('/kejadian/{kejadian}/penanganan', [KejadianController::class, 'penanganan'])->name('kejadian.penanganan');
    Route::resource('kejadian', KejadianController::class)->parameters(['kejadian' => 'kejadian']);

    Route::get('/petugas', [PetugasController::class, 'index'])->name('petugas.index');

    // master data: semua user internal boleh melihat, hanya pengelola yang mengubah (dicek di controller)
    Route::prefix('master')->name('master.')->group(function () {
        Route::resource('daerah-irigasi', DaerahIrigasiController::class)->parameters(['daerah-irigasi' => 'di']);
        Route::resource('daerah-irigasi.sub', SubDaerahIrigasiController::class)
            ->parameters(['daerah-irigasi' => 'di', 'sub' => 'sub'])->except(['index', 'show'])->shallow();
        Route::resource('jadwal-tanam', JadwalTanamController::class)->parameters(['jadwal-tanam' => 'jadwal'])->except(['show']);
        Route::resource('p3a', P3aController::class)->except(['show']);
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

    Route::get('/audit', AuditController::class)->name('audit')->middleware('can:lihat-audit');
});
