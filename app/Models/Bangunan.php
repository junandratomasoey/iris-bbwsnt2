<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Infrastruktur irigasi dalam D.I.: bendung, intake, saluran, bangunan bagi/sadap, dll. */
class Bangunan extends Model
{
    use LogsActivity;

    protected $table = 'bangunan';

    public const JENIS = [
        'bendungan' => 'Bendungan', 'bendung' => 'Bendung', 'embung' => 'Embung', 'captering' => 'Captering',
        'free_intake' => 'Free Intake', 'saluran_primer' => 'Saluran Primer', 'saluran_sekunder' => 'Saluran Sekunder',
        'bagi' => 'Bangunan Bagi', 'sadap' => 'Bangunan Sadap', 'bagi_sadap' => 'Bangunan Bagi-Sadap', 'pintu' => 'Pintu Air',
        'talang' => 'Talang', 'gorong' => 'Gorong-gorong', 'lainnya' => 'Lainnya',
    ];

    /** Kategori kondisi fisik jaringan irigasi (Permen PUPR 12/2015 & ePAKSI). */
    public const KONDISI = [
        'baik' => 'Baik', 'rusak_ringan' => 'Rusak ringan', 'rusak_sedang' => 'Rusak sedang', 'rusak_berat' => 'Rusak berat',
    ];

    public const WARNA_KONDISI = [
        'baik' => '#4E8A2E', 'rusak_ringan' => '#D4A017', 'rusak_sedang' => '#D9622B', 'rusak_berat' => '#B3261E',
    ];

    public const MAKS_FOTO = 12;

    protected $fillable = ['daerah_irigasi_id', 'sub_daerah_irigasi_id', 'nama', 'jenis', 'lokasi', 'kondisi', 'kondisi_per', 'lat', 'lng', 'keterangan'];

    protected $casts = ['lat' => 'float', 'lng' => 'float', 'kondisi_per' => Tanggal::class];

    protected static function booted(): void
    {
        static::deleting(fn (Bangunan $b) => $b->foto()->get()->each->delete());
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable')->latest('id');
    }

    public function getJenisLabelAttribute(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    public function getKondisiLabelAttribute(): string
    {
        return self::KONDISI[$this->kondisi] ?? 'Belum dinilai';
    }

    public function getWarnaKondisiAttribute(): string
    {
        return self::WARNA_KONDISI[$this->kondisi] ?? '#9AA4A8';
    }
}
