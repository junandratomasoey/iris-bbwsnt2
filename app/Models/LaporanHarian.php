<?php

namespace App\Models;

use App\Casts\Tanggal;
use App\Models\Concerns\LogsActivity;
use App\Support\KodeIsian;
use App\Support\Role;
use App\Support\StatusKondisi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Collection;

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
        'tanggal', 'jam', 'jenis', 'alasan', 'alasan_lain', 'sub_daerah_irigasi_id', 'user_id', 'mt', 'cuaca', 'hujan_7hari',
        'tindakan_operasi', 'atas_perintah', 'debit_intake_lps', 'tma_cm', 'kondisi_saluran',
        'bukaan_pintu_persen', 'fase_tanaman', 'luas_tanam_ha', 'luas_padi_ha', 'luas_palawija_ha', 'luas_horti_ha', 'jenis_palawija', 'jenis_horti', 'luas_hijau_ha', 'luas_kering_ha', 'luas_panen_ha',
        'luas_puso_ha', 'kendala', 'usulan', 'dokumentasi', 'status', 'lat', 'lng', 'status_verifikasi', 'catatan_verifikasi', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected $casts = [
        'tanggal' => Tanggal::class,
        'diverifikasi_pada' => 'datetime',
        'dokumentasi' => 'array',
        'lat' => 'float',
        'lng' => 'float',
    ];

    /** Kondisi tanaman per petak tersier (Bagian C). */
    public function petak(): HasMany
    {
        return $this->hasMany(LaporanPetak::class);
    }

    /** Hitung ulang total luas & status laporan dari baris petaknya. */
    public function hitungTotal(): void
    {
        $baris = $this->petak()->get();
        $tanam = $baris->reject->bera();
        $jumlah = fn ($kumpulan, $k) => round($kumpulan->sum(fn ($b) => (float) $b->$k), 2);
        $panen = round($tanam->filter(fn ($b) => $b->fase === 6)->sum('luas_tanam_ha') + $tanam->filter(fn ($b) => $b->fase !== 6)->sum('luas_panen_ha'), 2);
        $palawija = $tanam->whereIn('jenis_tanaman', ['J', 'K', 'Pl']);
        $this->forceFill([
            'luas_tanam_ha' => $jumlah($tanam, 'luas_tanam_ha'),
            'luas_padi_ha' => $jumlah($tanam->where('jenis_tanaman', 'P'), 'luas_tanam_ha'),
            'luas_palawija_ha' => $jumlah($palawija, 'luas_tanam_ha'),
            'luas_horti_ha' => 0,
            'jenis_palawija' => $palawija->pluck('jenis_tanaman')->unique()->map(fn ($k) => KodeIsian::JENIS_TANAMAN[$k])->implode(', ') ?: null,
            'jenis_horti' => null,
            'luas_kering_ha' => $jumlah($baris, 'luas_kering_ha'),
            'luas_panen_ha' => $panen,
            'luas_puso_ha' => $jumlah($baris, 'luas_puso_ha'),
            'luas_hijau_ha' => max(0, round($jumlah($tanam, 'luas_tanam_ha') - $jumlah($baris, 'luas_kering_ha') - $panen, 2)),
            'status' => $baris->reduce(fn ($s, $b) => StatusKondisi::maks($s, $b->status ?? $b->hitungStatus()), $baris->isEmpty() ? null : StatusKondisi::KOSONG),
        ])->save();
    }

    /** "Vegetatif awal (0–30 HST) · Sudah panen" — fase yang tercatat di petak-petaknya. */
    public function ringkasFase(): ?string
    {
        $fase = $this->petak->pluck('fase')->filter(fn ($f) => $f !== null)->unique()->sort()->map(fn ($f) => isset(KodeIsian::FASE[$f]) ? preg_replace('/\s*\(.*\)$/', '', KodeIsian::FASE[$f]) : null)->filter();
        if ($fase->isEmpty()) {
            return self::FASE[$this->fase_tanaman] ?? null;
        }

        return $fase->implode(' · ');
    }

    /** Jumlah petak dengan kecukupan air kurang/kering. */
    public function petakKekuranganAir(): int
    {
        return $this->petak->whereIn('kecukupan_air', ['KR', 'KS', 'KB'])->count();
    }

    public function subDaerahIrigasi(): BelongsTo
    {
        return $this->belongsTo(SubDaerahIrigasi::class);
    }

    /** Debit & TMA pintu yang diisi bersama laporan ini. */
    public function sesiPintu(): HasOne
    {
        return $this->hasOne(SesiPintu::class);
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
        return StatusKondisi::dariLaporan($this);
    }

    public function getJenisLabelAttribute(): string
    {
        $alasan = $this->alasan === 'lainnya' ? $this->alasan_lain : (KodeIsian::ALASAN_PPA[$this->alasan] ?? null);

        return (KodeIsian::JENIS_LAPOR[$this->jenis] ?? 'Rutin').($this->jenis === 'insidental' && $alasan ? ' · '.$alasan : '');
    }

    /**
     * Siapa yang memverifikasi laporan ini: Manager D.I. dan Koordinator Satgas wilayahnya.
     * Bila keduanya belum ada, Admin OP.
     *
     * @return Collection<int, User>
     */
    public function calonVerifikator()
    {
        $sub = $this->subDaerahIrigasi()->with('daerahIrigasi.kabupaten:id,wilayah_satgas_id')->first();
        $diId = $sub?->daerah_irigasi_id;
        $satgasId = $sub?->daerahIrigasi?->kabupaten?->wilayah_satgas_id;

        $orang = User::aktif()->where(fn ($q) => $q
            ->where(fn ($m) => $m->where('role', Role::MANAGER)->where('daerah_irigasi_id', $diId))
            ->when($satgasId, fn ($w) => $w->orWhere(fn ($k) => $k->where('role', Role::KOORDINATOR)->where('wilayah_satgas_id', $satgasId))))
            ->orderByRaw("CASE role WHEN 'manager' THEN 0 ELSE 1 END")->orderBy('name')->get();

        return $orang->isNotEmpty() ? $orang : User::aktif()->where('role', Role::ADMIN_OP)->orderBy('name')->get();
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
