<?php

namespace App\Services;

use App\Models\Foto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Simpan foto lapangan: diperkecil (maks 1600 px) & dikompres JPEG dengan GD agar hemat kuota hosting. */
class FotoService
{
    public const MAKS_SISI = 1600;

    public const KUALITAS = 72;

    /** @param UploadedFile[] $files */
    public function simpan(Model $pemilik, array $files, string $folder, ?float $lat = null, ?float $lng = null): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $folder.'/'.now()->format('Y/m').'/'.Str::uuid().'.jpg';
            Storage::disk('uploads')->put($path, $this->kompres($file));
            $pemilik->foto()->create(['path' => $path, 'lat' => $lat, 'lng' => $lng]);
        }
    }

    private function kompres(UploadedFile $file): string
    {
        $raw = file_get_contents($file->getRealPath());
        if (! function_exists('imagecreatefromstring') || ! ($img = @imagecreatefromstring($raw))) {
            return $raw;
        }
        $img = $this->putarSesuaiExif($img, $file);
        [$w, $h] = [imagesx($img), imagesy($img)];
        $skala = min(1, self::MAKS_SISI / max($w, $h));
        if ($skala < 1) {
            $baru = imagecreatetruecolor((int) round($w * $skala), (int) round($h * $skala));
            imagecopyresampled($baru, $img, 0, 0, 0, 0, imagesx($baru), imagesy($baru), $w, $h);
            imagedestroy($img);
            $img = $baru;
        }
        ob_start();
        imagejpeg($img, null, self::KUALITAS);
        imagedestroy($img);

        return ob_get_clean();
    }

    private function putarSesuaiExif($img, UploadedFile $file)
    {
        if (! function_exists('exif_read_data') || ! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'])) {
            return $img;
        }
        $o = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;

        return match ($o) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }

    public function hapus(Foto $foto): void
    {
        $foto->delete();
    }
}
