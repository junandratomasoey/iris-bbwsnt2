<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class SubDaerahIrigasi extends Model
{
    use LogsActivity;

    protected $table = 'sub_daerah_irigasi';

    /** Foto profil / kondisi Sub-D.I. yang tampil di peta. */
    public const MAKS_FOTO_PROFIL = 4;

    protected $fillable = ['daerah_irigasi_id', 'nama', 'luas_ha', 'luas_fungsional_ha', 'lokasi', 'kecamatan', 'desa', 'saluran', 'panjang_saluran_m',
        'kabupaten_id', 'lat', 'lng', 'geometri', 'keterangan'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'luas_ha' => 'float', 'luas_fungsional_ha' => 'float', 'panjang_saluran_m' => 'float', 'geometri' => 'array'];

    public array $tidakDicatat = ['geometri'];

    protected static function booted(): void
    {
        static::deleting(fn (SubDaerahIrigasi $s) => $s->fotoProfil()->get()->each->delete());
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function fotoProfil(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable')->where('keterangan', 'profil')->orderBy('urutan')->orderBy('id');
    }

    public function p3a(): HasMany
    {
        return $this->hasMany(P3a::class);
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class);
    }

    public function petugas(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function bangunan(): HasMany
    {
        return $this->hasMany(Bangunan::class);
    }

    public function pintuAir(): HasMany
    {
        return $this->hasMany(PintuAir::class);
    }

    public function laporanHarian(): HasMany
    {
        return $this->hasMany(LaporanHarian::class);
    }

    public function laporanTerakhir(): HasOne
    {
        return $this->hasOne(LaporanHarian::class)->ofMany(['tanggal' => 'max', 'id' => 'max']);
    }

    /** Titik peta: koordinat sendiri, atau titik tengah D.I. bila belum diisi. */
    public function titik(): ?array
    {
        if ($this->lat && $this->lng) {
            return [$this->lat, $this->lng, true];
        }
        $di = $this->daerahIrigasi;

        return $di && $di->lat ? [$di->lat, $di->lng, false] : null;
    }
}
