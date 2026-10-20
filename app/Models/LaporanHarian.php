<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class LaporanHarian extends Model
{
    use LogsActivity;

    protected $table = 'laporan_harian';

    public const KONDISI = ['penuh' => 'Penuh', 'normal' => 'Normal', 'kurang' => 'Kurang', 'kering' => 'Kering'];

    public const FASE = [
        'bera' => 'Bera / tidak ditanam', 'persiapan' => 'Persiapan lahan', 'vegetatif' => 'Vegetatif',
        'generatif' => 'Generatif (bunting–berbunga)', 'pemasakan' => 'Pemasakan', 'panen' => 'Panen',
    ];

    public const KOMODITAS = ['padi' => 'Padi', 'palawija' => 'Palawija', 'horti' => 'Hortikultura'];

    /** Pilihan cepat jenis tanaman (boleh diketik selain ini). */
    public const JENIS = [
        'palawija' => ['Jagung', 'Kedelai', 'Kacang tanah', 'Kacang hijau', 'Ubi kayu', 'Ubi jalar', 'Sorgum'],
        'horti' => ['Cabai', 'Bawang merah', 'Tomat', 'Sawi', 'Kangkung', 'Terong', 'Kacang panjang', 'Semangka'],
    ];

    public const VERIFIKASI = ['menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'perbaikan' => 'Perlu perbaikan'];

    protected $fillable = [
        'tanggal', 'sub_daerah_irigasi_id', 'pintu_air_id', 'petak_kunci', 'user_id', 'mt', 'debit_intake_lps', 'tma_cm', 'kondisi_saluran',
        'bukaan_pintu_persen', 'fase_tanaman', 'luas_tanam_ha', 'luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'jenis_palawija', 'jenis_horti', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha',
        'luas_puso_ha', 'kendala', 'lat', 'lng', 'status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected $casts = [
        'tanggal' => Tanggal::class,
        'diverifikasi_pada' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function booted(): void
    {
        static::saving(fn (LaporanHarian $l) => $l->petak_kunci = (int) ($l->pintu_air_id ?? 0));
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    /** Debit & TMA pintu yang diisi bersama laporan ini. */
    public function sesiPintu(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(SesiPintu::class);
    }

    /** Petak tersier (diwakili pintu tersiernya). */
    public function petak(): BelongsTo
    {
        return $this->belongsTo(PintuAir::class, 'pintu_air_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function foto(): MorphMany
    {
        return $this->morphMany(Foto::class, 'fotoable');
    }

    public function status(): string
    {
        // laporan gabungan beberapa petak (KondisiService) membawa status petak terberat
        return $this->getAttribute('status_gabungan') ?? StatusKondisi::dariLaporan($this);
    }

    /**
     * Siapa yang memverifikasi laporan ini: Manager D.I. dan Koordinator Satgas wilayahnya.
     * Bila keduanya belum ada, Admin OP.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function calonVerifikator()
    {
        $sub = $this->subDaerahIrigasi()->with('daerahIrigasi.kabupaten:id,wilayah_satgas_id')->first();
        $diId = $sub?->daerah_irigasi_id;
        $satgasId = $sub?->daerahIrigasi?->kabupaten?->wilayah_satgas_id;

        $orang = User::aktif()->where(fn ($q) => $q
            ->where(fn ($m) => $m->where('role', \App\Support\Role::MANAGER)->where('daerah_irigasi_id', $diId))
            ->when($satgasId, fn ($w) => $w->orWhere(fn ($k) => $k->where('role', \App\Support\Role::KOORDINATOR)->where('wilayah_satgas_id', $satgasId))))
            ->orderByRaw("CASE role WHEN 'manager' THEN 0 ELSE 1 END")->orderBy('name')->get();

        return $orang->isNotEmpty() ? $orang : User::aktif()->where('role', \App\Support\Role::ADMIN_OP)->orderBy('name')->get();
    }

    /**
     * Rincian komoditas yang ditanam, mis. [['kode' => 'palawija', 'label' => 'Palawija', 'luas' => 30.0, 'jenis' => 'Jagung']].
     *
     * @return array<int, array{kode: string, label: string, luas: float, jenis: ?string}>
     */
    public function komoditas(): array
    {
        $hasil = [];
        foreach (self::KOMODITAS as $k => $label) {
            $luas = (float) $this->{"luas_{$k}_ha"};
            if ($luas > 0) {
                $hasil[] = ['kode' => $k, 'label' => $label, 'luas' => $luas, 'jenis' => $k === 'padi' ? null : $this->{"jenis_$k"}];
            }
        }

        return $hasil;
    }

    /** "Padi 120 Ha · Palawija 30 Ha (Jagung)" */
    public function ringkasKomoditas(): string
    {
        return collect($this->komoditas())->map(fn ($k) => $k['label'].' '.angka($k['luas'], floor($k['luas']) == $k['luas'] ? 0 : 2).' Ha'.($k['jenis'] ? " ({$k['jenis']})" : ''))->implode(' · ');
    }

    public function terkunci(): bool
    {
        return $this->status_verifikasi === 'disetujui';
    }
}
