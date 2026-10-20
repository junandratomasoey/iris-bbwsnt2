<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Laporan air harian Manager D.I.: sungai, intake, dan saluran. Satu per D.I. per hari. */
class LaporanAir extends Model
{
    use LogsActivity;

    protected $table = 'laporan_air';

    public const MAKS_FOTO = 5;

    /** Laporan boleh diubah pengisinya sampai sekian hari setelah tanggal laporan. */
    public const BATAS_UBAH_HARI = 7;

    protected $fillable = [
        'tanggal', 'daerah_irigasi_id', 'user_id', 'mt', 'debit_sungai_m3s', 'tma_sungai_cm', 'debit_intake_lps', 'tma_intake_cm',
        'kondisi_saluran', 'bukaan_pintu_persen', 'catatan', 'lat', 'lng',
    ];

    protected $casts = [
        'tanggal' => Tanggal::class, 'lat' => 'float', 'lng' => 'float',
        'debit_sungai_m3s' => 'float', 'tma_sungai_cm' => 'float', 'debit_intake_lps' => 'float', 'tma_intake_cm' => 'float',
    ];

    protected static function booted(): void
    {
        static::deleting(fn (LaporanAir $l) => $l->foto()->get()->each->delete());
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function status(): string
    {
        return StatusKondisi::dariSaluran($this->kondisi_saluran);
    }

    public function terkunci(): bool
    {
        return $this->tanggal->copy()->addDays(self::BATAS_UBAH_HARI)->lt(today());
    }
}
