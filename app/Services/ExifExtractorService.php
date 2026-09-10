<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ExifExtractorService
{
    public function extract(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $imageInfo = @getimagesize($path);
        $raw = function_exists('exif_read_data') ? (@exif_read_data($path, null, true) ?: []) : [];
        $width = $imageInfo[0] ?? null;
        $height = $imageInfo[1] ?? null;
        $gps = $this->coordinates($raw['GPS'] ?? []);

        return [
            'file' => [
                'name' => $this->safe($file->getClientOriginalName()),
                'type' => $this->safe($imageInfo['2'] ?? $file->getClientOriginalExtension()),
                'mime' => $this->safe($file->getMimeType()),
                'size' => $this->formatBytes($file->getSize()),
                'size_bytes' => $file->getSize(),
            ],
            'image' => [
                'width' => $width ? (string) $width.' px' : null,
                'height' => $height ? (string) $height.' px' : null,
                'aspect_ratio' => $width && $height ? $this->ratio($width, $height) : null,
                'color_space' => $this->safe($raw['EXIF']['ColorSpace'] ?? $raw['COMPUTED']['ColorSpace'] ?? null),
                'bits_per_sample' => $this->safe($raw['COMPUTED']['BitsPerSample'] ?? null),
            ],
            'camera' => [
                'make' => $this->safe($raw['IFD0']['Make'] ?? null),
                'model' => $this->safe($raw['IFD0']['Model'] ?? null),
                'lens_model' => $this->safe($raw['EXIF']['LensModel'] ?? null),
                'lens_make' => $this->safe($raw['EXIF']['LensMake'] ?? null),
                'software' => $this->safe($raw['IFD0']['Software'] ?? null),
                'firmware' => $this->safe($raw['EXIF']['Firmware'] ?? null),
                'serial_number' => $this->safe($raw['EXIF']['BodySerialNumber'] ?? $raw['EXIF']['SerialNumber'] ?? null),
            ],
            'photo' => [
                'date_taken' => $this->safe($raw['EXIF']['DateTimeOriginal'] ?? $raw['IFD0']['DateTime'] ?? null),
                'exposure_time' => $this->exposure($raw['EXIF']['ExposureTime'] ?? null),
                'f_number' => $this->aperture($raw['EXIF']['FNumber'] ?? null),
                'iso' => $this->safe($raw['EXIF']['ISOSpeedRatings'] ?? null),
                'focal_length' => $this->focal($raw['EXIF']['FocalLength'] ?? null),
                'exposure_bias' => $this->safe($raw['EXIF']['ExposureBiasValue'] ?? null),
                'flash' => $this->safe($raw['EXIF']['Flash'] ?? null),
                'white_balance' => $this->safe($raw['EXIF']['WhiteBalance'] ?? null),
                'metering_mode' => $this->safe($raw['EXIF']['MeteringMode'] ?? null),
                'exposure_program' => $this->safe($raw['EXIF']['ExposureProgram'] ?? null),
            ],
            'gps' => $gps,
            'other' => [
                'orientation' => $this->safe($raw['IFD0']['Orientation'] ?? null),
                'artist' => $this->safe($raw['IFD0']['Artist'] ?? null),
                'copyright' => $this->safe($raw['IFD0']['Copyright'] ?? null),
                'image_description' => $this->safe($raw['IFD0']['ImageDescription'] ?? null),
                'user_comment' => $this->safe($raw['EXIF']['UserComment'] ?? null),
                'xmp_iptc' => $this->safe($raw['XMP']['XML'] ?? $raw['IPTC']['Caption'] ?? null),
            ],
        ];
    }

    public function toRows(array $metadata): array
    {
        $labels = [
            'file' => 'General', 'image' => 'General', 'camera' => 'Camera',
            'photo' => 'Photo', 'gps' => 'GPS', 'other' => 'Other',
        ];
        $rows = [];
        foreach ($metadata as $category => $properties) {
            foreach ($properties as $property => $value) {
                if ($property === 'size_bytes') {
                    continue;
                }
                $rows[] = ['category' => $labels[$category] ?? ucfirst($category), 'property' => ucwords(str_replace('_', ' ', $property)), 'value' => $value ?: 'Not available'];
            }
        }

        return $rows;
    }

    public function toJson(array $metadata): string
    {
        return json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function coordinates(array $gps): array
    {
        $lat = $this->dms($gps['GPSLatitude'] ?? null, $gps['GPSLatitudeRef'] ?? null);
        $lng = $this->dms($gps['GPSLongitude'] ?? null, $gps['GPSLongitudeRef'] ?? null);

        return [
            'latitude' => $lat,
            'longitude' => $lng,
            'altitude' => $this->safe($gps['GPSAltitude'] ?? null),
            'date' => $this->safe($gps['GPSDateStamp'] ?? null),
            'time' => $this->safe($gps['GPSTimeStamp'] ?? null),
            'direction' => $this->safe($gps['GPSImgDirection'] ?? null),
            'available' => $lat !== null && $lng !== null,
        ];
    }

    private function dms(mixed $value, mixed $ref): ?float
    {
        if (! is_array($value) || count($value) < 3) {
            return null;
        }
        $parts = array_map(fn ($part) => $this->fraction($part), array_values($value));
        if ($parts[0] === null || $parts[1] === null || $parts[2] === null) {
            return null;
        }
        $decimal = $parts[0] + ($parts[1] / 60) + ($parts[2] / 3600);

        return in_array(strtoupper((string) $ref), ['S', 'W'], true) ? -$decimal : $decimal;
    }

    private function fraction(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('/^(-?\d+(?:\.\d+)?)\s*\/\s*(\d+(?:\.\d+)?)$/', trim($value), $match)) {
            return (float) $match[1] / (float) $match[2];
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function safe(mixed $value): ?string
    {
        if (is_array($value)) {
            return implode(', ', array_map(fn ($item) => (string) $item, $value));
        }
        if ($value === null || $value === '') {
            return null;
        }

        return trim(preg_replace('/[\x00-\x1F\x7F]/', '', (string) $value));
    }

    private function ratio(int $width, int $height): string
    {
        $a = $width;
        $b = $height;
        while ($b !== 0) {
            $remainder = $a % $b;
            $a = $b;
            $b = $remainder;
        }

return ($width / $a).':'.($height / $a);
    }

    private function exposure(mixed $value): ?string
    {
        $value = $this->fraction($value);

        return $value === null ? null : ($value < 1 ? '1/'.max(1, round(1 / $value)).' s' : $value.' s');
    }

    private function aperture(mixed $value): ?string
    {
        $value = $this->fraction($value);

        return $value === null ? null : 'f/'.rtrim(rtrim(number_format($value, 1), '0'), '.');
    }

    private function focal(mixed $value): ?string
    {
        $value = $this->fraction($value);

        return $value === null ? null : rtrim(rtrim(number_format($value, 1), '0'), '.').' mm';
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $index = 0;
        while ($bytes >= 1024 && $index < 3) {
            $bytes /= 1024;
            $index++;
        }

return round($bytes, 1).' '.$units[$index];
    }
}
