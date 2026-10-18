<?php

namespace App\Policies;

use App\Models\Kejadian;
use App\Models\User;
use App\Support\Role;

class KejadianPolicy
{
    public function view(User $user, Kejadian $k): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return ! $user->hasRole(Role::PIMPINAN);
    }

    public function update(User $user, Kejadian $k): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_OP, Role::KOORDINATOR)
            || ($k->user_id === $user->id && $k->status_penanganan === 'Perlu Penanganan');
    }

    public function delete(User $user, Kejadian $k): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_OP) || ($k->user_id === $user->id && $k->status_penanganan === 'Perlu Penanganan');
    }

    /** Ubah status penanganan & luas tertangani. */
    public function tangani(User $user, Kejadian $k): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_OP, Role::KOORDINATOR);
    }
}
