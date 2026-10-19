<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Pintu air pada jaringan irigasi. Rentang normal debit & TMA diisi sendiri per pintu
 * (dari kebutuhan air/rencana tata tanam); status pembacaan dihitung dari rentang ini.
 */
class PintuAir extends Model
{
    use LogsActivity;

    protected $table = 'pintu_air';

    public const JENIS = [
        'intake' => 'Pintu pengambilan (intake)',
        'penguras' => 'Pintu penguras',
        'bagi' => 'Pintu bagi',
        'sadap' => 'Pintu sadap',
        'tersier' => 'Pintu tersier',
        'suplesi' => 'Pintu suplesi',
        'pembuang' => 'Pintu pembuang',
        'lainnya' => 'Lainnya',
    ];

    protected $fillable = [
        'daerah_irigasi_id', 'sub_daerah_irigasi_id', 'bangunan_id', 'kode', 'nama', 'jenis', 'saluran', 'luas_layanan_ha',
        'debit_min_lps', 'debit_maks_lps', 'tma_min_cm', 'tma_maks_cm', 'urutan', 'aktif', 'lat', 'lng', 'keterangan',
    ];

    protected $casts = [
        'aktif' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'luas_layanan_ha' => 'float',
        'debit_min_lps' => 'float', 'debit_maks_lps' => 'float', 'tma_min_cm' => 'float', 'tma_maks_cm' => 'float',
    ];

    public function daerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(DaerahIrigasi::class);
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    public function bangunan(): BelongsTo
    {
        return $this->belongsTo(Bangunan::class);
    }

    public function pembacaan(): HasMany
    {
        return $this->hasMany(PembacaanPintu::class);
    }

    public function pembacaanTerakhir(): HasOne
    {
        return $this->hasOne(PembacaanPintu::class)->ofMany(['tanggal' => 'max', 'id' => 'max']);
    }

    public function scopeUrut(Builder $q): Builder
    {
        return $q->orderByRaw('sub_daerah_irigasi_id is not null')->orderBy('sub_daerah_irigasi_id')->orderBy('urutan')->orderBy('kode');
    }

    /** Pintu yang terjangkau oleh daftar Sub-D.I. (null = semua): pintu di Sub-D.I. itu + pintu tingkat D.I. (tanpa Sub-D.I.). */
    public function scopeTerjangkau(Builder $q, ?array $subIds): Builder
    {
        if ($subIds === null) {
            return $q;
        }
        $diIds = SubDaerahIrigasi::whereIn('id', $subIds)->distinct()->pluck('daerah_irigasi_id');

        return $q->where(fn ($w) => $w->whereIn('sub_daerah_irigasi_id', $subIds)
            ->orWhere(fn ($x) => $x->whereNull('sub_daerah_irigasi_id')->whereIn('daerah_irigasi_id', $diIds)));
    }

    public function getJenisLabelAttribute(): string
    {
        return self::JENIS[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    public function getLabelAttribute(): string
    {
        return $this->kode.($this->nama ? ' · '.$this->nama : '');
    }

    public function punyaRentangDebit(): bool
    {
        return $this->debit_min_lps !== null || $this->debit_maks_lps !== null;
    }

    /** 'kurang' | 'normal' | 'berlebih' | null (rentang belum diisi / tidak ada angka). */
    public function statusDebit(?float $debit): ?string
    {
        return self::bandingkan($debit, $this->debit_min_lps, $this->debit_maks_lps, 'kurang', 'berlebih');
    }

    /** 'rendah' | 'normal' | 'tinggi' | null. */
    public function statusTma(?float $tma): ?string
    {
        return self::bandingkan($tma, $this->tma_min_cm, $this->tma_maks_cm, 'rendah', 'tinggi');
    }

    private static function bandingkan(?float $nilai, ?float $min, ?float $maks, string $bawah, string $atas): ?string
    {
        if ($nilai === null || ($min === null && $maks === null)) {
            return null;
        }
        if ($min !== null && $nilai < $min) {
            return $bawah;
        }
        if ($maks !== null && $nilai > $maks) {
            return $atas;
        }

        return 'normal';
    }

    public static function rentangTeks(?float $min, ?float $maks, string $satuan): string
    {
        $f = fn ($v) => rtrim(rtrim(number_format($v, 2, ',', '.'), '0'), ',');

        return match (true) {
            $min !== null && $maks !== null => $f($min).'–'.$f($maks).' '.$satuan,
            $min !== null => '≥ '.$f($min).' '.$satuan,
            $maks !== null => '≤ '.$f($maks).' '.$satuan,
            default => '—',
        };
    }
}
