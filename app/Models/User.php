<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\HakAkses;
use App\Support\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, LogsActivity, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'no_hp', 'password', 'role', 'jabatan',
        'wilayah_satgas_id', 'daerah_irigasi_id', 'status_kontak', 'is_active', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'role' => 'petugas',
        'is_active' => true,
        'must_change_password' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function wilayahSatgas(): BelongsTo
    {
        return $this->belongsTo(WilayahSatgas::class);
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function subDaerahIrigasi(): BelongsToMany
    {
        return $this->belongsToMany(SubDaerahIrigasi::class);
    }

    public function laporanHarian(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function izinPengguna(): HasMany
    {
        return $this->hasMany(IzinPengguna::class);
    }

    /** @var array<string,bool>|null */
    private ?array $izinKhususCache = null;

    /** Pengecualian izin orang ini: izin => boleh. */
    public function izinKhusus(): array
    {
        if ($this->izinKhususCache === null) {
            $this->izinKhususCache = $this->exists
                ? IzinPengguna::where('user_id', $this->id)->pluck('boleh', 'izin')->map(fn ($b) => (bool) $b)->all()
                : [];
        }

        return $this->izinKhususCache;
    }

    public function lupakanIzin(): void
    {
        $this->izinKhususCache = null;
    }

    /** Apakah user punya izin form/fitur ini (lihat App\Support\Izin). */
    public function boleh(string $izin): bool
    {
        return HakAkses::boleh($this, $izin);
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isGlobal(): bool
    {
        return $this->hasRole(...Role::GLOBAL);
    }

    public function roleLabel(): string
    {
        return Role::label($this->role);
    }

    public function waLink(?string $pesan = null): ?string
    {
        return wa_link($this->no_hp, $pesan);
    }

    /**
     * ID Sub-D.I. yang boleh dilihat user. null = semua.
     *
     * @return array<int>|null
     */
    public function subIdsTerjangkau(): ?array
    {
        if ($this->isGlobal()) {
            return null;
        }

        return once(function () {
            $q = SubDaerahIrigasi::query();
            if ($this->hasRole(Role::KOORDINATOR) && $this->wilayah_satgas_id) {
                $q->whereHas('daerahIrigasi.kabupaten', fn ($k) => $k->where('wilayah_satgas_id', $this->wilayah_satgas_id));
            } elseif ($this->hasRole(Role::MANAGER) && $this->daerah_irigasi_id) {
                $q->where('daerah_irigasi_id', $this->daerah_irigasi_id);
            } else {
                $ids = $this->subDaerahIrigasi()->pluck('sub_daerah_irigasi.id')->all();
                if ($ids) {
                    return $ids;
                }
                if (! $this->daerah_irigasi_id) {
                    return [];
                }
                $q->where('daerah_irigasi_id', $this->daerah_irigasi_id);
            }

            return $q->pluck('id')->all();
        });
    }

    /** ID D.I. dalam jangkauan user. null = semua. */
    public function diIdsTerjangkau(): ?array
    {
        $subIds = $this->subIdsTerjangkau();
        if ($subIds === null) {
            return null;
        }

        return once(fn () => SubDaerahIrigasi::whereIn('id', $subIds)->distinct()->pluck('daerah_irigasi_id')
            ->push($this->daerah_irigasi_id)->filter()->unique()->values()->all());
    }

    /** Boleh menambah/mengubah pintu air & skema di D.I. ini. */
    public function bolehKelolaPintu(int $diId): bool
    {
        if (! $this->boleh('kelola-pintu')) {
            return false;
        }
        $ids = $this->diIdsTerjangkau();

        return $ids === null || in_array($diId, $ids, true);
    }

    /** Sub-D.I. yang boleh diisi (laporan harian / pembacaan pintu) oleh user. null = semua. */
    public function subIdsPelaporan(string $izin = 'lapor'): ?array
    {
        if (! $this->boleh($izin)) {
            return [];
        }

        return $this->subIdsTerjangkau();
    }

    public function bolehLapor(int $subId): bool
    {
        $ids = $this->subIdsPelaporan();

        return $ids === null || in_array($subId, $ids, true);
    }

    public function bolehLihatSub(?int $subId): bool
    {
        $ids = $this->subIdsTerjangkau();

        return $ids === null || ($subId && in_array($subId, $ids, true));
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function scopeLapangan(Builder $q): Builder
    {
        return $q->whereIn('role', [Role::PETUGAS, Role::POB, Role::MANAGER]);
    }
}
