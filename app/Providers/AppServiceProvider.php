<?php

namespace App\Providers;

use App\Models\User;
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

        Gate::define('kelola-master', fn (User $u) => $u->hasRole(...Role::PENGELOLA));
        Gate::define('kelola-pengguna', fn (User $u) => $u->hasRole(...Role::PENGELOLA));
        Gate::define('verifikasi', fn (User $u) => $u->hasRole(...Role::VERIFIKATOR));
        Gate::define('lapor', fn (User $u) => $u->hasRole(...Role::PELAPOR));
        Gate::define('lihat-audit', fn (User $u) => $u->hasRole(Role::SUPER_ADMIN));
        Gate::define('kelola-kejadian', fn (User $u) => $u->hasRole(Role::SUPER_ADMIN, Role::ADMIN_OP, Role::KOORDINATOR));
    }
}
