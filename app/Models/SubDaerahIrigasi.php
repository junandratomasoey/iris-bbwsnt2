<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SubDaerahIrigasi extends Model
{
    use LogsActivity;

    protected $table = 'sub_daerah_irigasi';

    protected $fillable = ['daerah_irigasi_id', 'nama', 'luas_ha', 'lokasi', 'kabupaten_id', 'lat', 'lng', 'keterangan'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'luas_ha' => 'float'];

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
