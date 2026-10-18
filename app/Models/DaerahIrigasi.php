<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DaerahIrigasi extends Model
{
    use LogsActivity;

    protected $table = 'daerah_irigasi';

    protected $fillable = [
        'kode', 'nama', 'alias', 'kabupaten_id', 'kewenangan', 'luas_permen_ha', 'luas_baku_ha',
        'luas_potensial_ha', 'luas_fungsional_ha', 'sumber_air', 'keterangan', 'lat', 'lng', 'tenaga_op',
    ];

    protected $casts = [
        'alias' => 'array',
        'tenaga_op' => 'array',
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (DaerahIrigasi $di) => $di->foto()->get()->each->delete());
    }

    public function kabupaten(): BelongsTo
    {
        return $this->belongsTo(Kabupaten::class);
    }

    public function subDaerahIrigasi(): HasMany
    {
        return $this->hasMany(SubDaerahIrigasi::class)->orderBy('nama');
    }

    public function bangunan(): HasMany
    {
        return $this->hasMany(Bangunan::class);
    }

    public function jadwalTanam(): HasMany
    {
        return $this->hasMany(JadwalTanam::class)->orderBy('tahun')->orderBy('mt');
    }

    public function petugas(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function peta(): HasMany
    {
        return $this->hasMany(PetaDi::class);
    }

    public function laporanHarian(): HasManyThrough
    {
        return $this->hasManyThrough(LaporanHarian::class, SubDaerahIrigasi::class);
    }

    /** Foto profil / kondisi awal (hasil walkthrough, ePAKSI), maks. MAKS_FOTO_PROFIL, urut sesuai pilihan admin. */
    public const MAKS_FOTO_PROFIL = 3;

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function fotoProfil(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable')->where('keterangan', 'profil')->orderBy('urutan')->orderBy('id');
    }

    public function getNamaLengkapAttribute(): string
    {
        return 'D.I. '.$this->nama;
    }

    /** Musim tanam yang sedang berjalan pada tanggal tertentu. */
    public function mtBerjalan(?\DateTimeInterface $tgl = null): ?JadwalTanam
    {
        $tgl = $tgl ? Carbon::instance($tgl) : today();
        $list = $this->relationLoaded('jadwalTanam') ? $this->jadwalTanam : $this->jadwalTanam()->get();

        return $list->first(fn ($j) => $tgl->between($j->mulai, $j->selesai));
    }
}
