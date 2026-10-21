<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use Carbon\Carbon;
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
        'harian' => 'Laporan PPA',
        'air' => 'Laporan Manager Air',
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

    /** Alasan yang bisa dipilih di form pembacaan pintu ('harian' otomatis dari laporan harian PPA). */
    public static function jenisPilihan(): array
    {
        return array_diff_key(self::JENIS, ['harian' => true, 'air' => true]);
    }

    protected $fillable = ['daerah_irigasi_id', 'user_id', 'laporan_harian_id', 'laporan_air_id', 'tanggal', 'jam', 'jenis', 'catatan', 'lat', 'lng'];

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

    public function laporanHarian(): BelongsTo
    {
        return $this->belongsTo(LaporanHarian::class);
    }

    public function laporanAir(): BelongsTo
    {
        return $this->belongsTo(LaporanAir::class);
    }

    /** Sesi yang dibuat dari laporan PPA / Manager diubah lewat laporannya. */
    public function dariLaporan(): bool
    {
        return (bool) ($this->laporan_harian_id || $this->laporan_air_id);
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
    public static function seninIni(): Carbon
    {
        return today()->startOfWeek(Carbon::MONDAY);
    }
}
