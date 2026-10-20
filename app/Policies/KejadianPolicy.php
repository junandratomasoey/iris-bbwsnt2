<?php

namespace App\Policies;

use App\Models\Kejadian;
use App\Models\User;
use App\Support\Role;

class KejadianPolicy
{
    public function view(User $user, Kejadian $k): bool
    {
        return Kejadian::whereKey($k->id)->terjangkau($user)->exists();
    }

    public function create(User $user): bool
    {
        return $user->boleh('lapor-kejadian');
    }

    public function update(User $user, Kejadian $k): bool
    {
        return $user->boleh('kelola-kejadian')
            || ($k->user_id === $user->id && $k->status_penanganan === 'Perlu Penanganan');
    }

    public function delete(User $user, Kejadian $k): bool
    {
        return $user->hasRole(Role::SUPER_ADMIN, Role::ADMIN_OP) || ($k->user_id === $user->id && $k->status_penanganan === 'Perlu Penanganan');
    }

    /** Tambah/hapus foto dokumentasi: pelapor sendiri, atau yang menangani (Admin OP, Koordinator). */
    public function foto(User $user, Kejadian $k): bool
    {
        return $k->user_id === $user->id || $this->tangani($user, $k);
    }

    /** Ubah status penanganan & luas tertangani. */
    public function tangani(User $user, Kejadian $k): bool
    {
        return $user->boleh('kelola-kejadian');
    }
}
