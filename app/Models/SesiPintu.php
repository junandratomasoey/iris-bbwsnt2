<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Satu kunjungan pembacaan pintu air di satu D.I. (rutin Senin atau kejadian khusus). */
class SesiPintu extends Model
{
    use LogsActivity;

    protected $table = 'sesi_pintu';

    public const JENIS = [
        'rutin' => 'Rutin mingguan (Senin)',
        'panen' => 'Panen',
        'pekerjaan' => 'Pekerjaan di sawah/saluran',
        'kejadian' => 'Kejadian khusus (kekeringan, banjir, kerusakan)',
        'lainnya' => 'Lainnya',
    ];

    /** Hari jadwal pembacaan rutin (Carbon: 1 = Senin). */
    public const HARI_RUTIN = 1;

    /** Pembacaan boleh diubah pengisinya sampai sekian hari setelah tanggal pembacaan. */
    public const BATAS_UBAH_HARI = 3;

    public const MAKS_FOTO = 6;

    protected $fillable = ['daerah_irigasi_id', 'user_id', 'tanggal', 'jam', 'jenis', 'catatan', 'lat', 'lng'];

    protected $casts = ['tanggal' => Tanggal::class, 'lat' => 'float', 'lng' => 'float'];

    protected static function booted(): void
    {
        static::deleting(fn (SesiPintu $s) => $s->foto()->get()->each->delete());
    }

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pembacaan(): HasMany
    {
        return $this->hasMany(PembacaanPintu::class);
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function getJenisLabelAttribute(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    public function terkunci(): bool
    {
        return $this->tanggal->copy()->addDays(self::BATAS_UBAH_HARI)->lt(today());
    }

    /** Senin minggu berjalan (awal kewajiban baca rutin). */
    public static function seninIni(): \Carbon\Carbon
    {
        return today()->startOfWeek(\Carbon\Carbon::MONDAY);
    }
}
