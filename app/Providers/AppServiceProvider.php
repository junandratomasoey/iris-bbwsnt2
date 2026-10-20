<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Izin;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Carbon::setLocale('id');
        Paginator::defaultView('pagination.iris');
        Model::preventLazyLoading(! app()->isProduction());

        foreach (Izin::kunci() as $izin) {
            Gate::define($izin, fn (User $u) => $u->boleh($izin));
        }
        // halaman Hak akses hanya Super Admin (tidak bisa dicabut agar tidak ada yang terkunci keluar)
        Gate::define('kelola-hak-akses', fn (User $u) => $u->hasRole(Role::SUPER_ADMIN));
        // log aktivitas hanya Super Admin (tidak bisa diatur di Hak akses)
        Gate::define('lihat-audit', fn (User $u) => $u->hasRole(Role::SUPER_ADMIN));
    }
}
