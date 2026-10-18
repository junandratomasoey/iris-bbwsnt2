<?php

namespace App\Policies;

use App\Models\LaporanHarian;
use App\Models\User;
use App\Support\Role;

class LaporanHarianPolicy
{
    public function view(User $user, LaporanHarian $l): bool
    {
        return $user->bolehLihatSub($l->sub_daerah_irigasi_id);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(...Role::PELAPOR);
    }

    public function update(User $user, LaporanHarian $l): bool
    {
        if ($user->hasRole(...Role::PENGELOLA)) {
            return true;
        }

        return $l->user_id === $user->id && ! $l->terkunci();
    }

    public function delete(User $user, LaporanHarian $l): bool
    {
        return $this->update($user, $l);
    }

    public function verify(User $user, LaporanHarian $l): bool
    {
        return $user->hasRole(...Role::VERIFIKATOR) && $user->bolehLihatSub($l->sub_daerah_irigasi_id);
    }
}
