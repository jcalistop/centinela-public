<?php

declare(strict_types=1);

namespace App\Support\Configuration;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Constraint;
use Intervention\Image\Facades\Image;
use Intervention\Image\Image as InterventionImage;
use TCG\Voyager\Facades\Voyager;

class VoyagerSettingMediaService
{
    public function disk(): string
    {
        return (string) config('voyager.storage.disk', 'private');
    }

    /**
     * @return array{mode: string, width: int|null, height: int|null, quality: int, upsize: bool}|null
     */
    public function resizeProfileFor(string $settingKey): ?array
    {
        $profiles = config('centinela.settings_media.profiles', []);

        if (! is_array($profiles) || ! isset($profiles[$settingKey]) || ! is_array($profiles[$settingKey])) {
            return null;
        }

        $profile = $profiles[$settingKey];
        $mode = $profile['mode'] ?? 'resize';

        if (! in_array($mode, ['fit', 'resize'], true)) {
            return null;
        }

        $width = isset($profile['width']) ? (int) $profile['width'] : null;
        $height = array_key_exists('height', $profile) && $profile['height'] !== null
            ? (int) $profile['height']
            : null;

        if ($mode === 'fit' && ($width === null || $height === null)) {
            return null;
        }

        if ($mode === 'resize' && $width === null && $height === null) {
            return null;
        }

        return [
            'mode' => $mode,
            'width' => $width,
            'height' => $height,
            'quality' => (int) ($profile['quality'] ?? config('centinela.settings_media.default_quality', 75)),
            'upsize' => (bool) ($profile['upsize'] ?? false),
        ];
    }

    public function storeImage(UploadedFile $file, ?string $settingKey = null): string
    {
        $path = $this->basePath();
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $this->uniqueFilename($file, $path);
        $fullPath = $path.$filename.'.'.$extension;
        $realPath = $file->getRealPath() ?: '';

        if ($extension === 'gif' && $this->isAnimatedGif($realPath)) {
            Storage::disk($this->disk())->put($fullPath, file_get_contents($realPath), 'public');

            return $fullPath;
        }

        $profile = $settingKey !== null ? $this->resizeProfileFor($settingKey) : null;
        $quality = $profile['quality'] ?? (int) config('centinela.settings_media.default_quality', 75);

        $image = Image::make($file)->orientate();
        $image = $profile !== null
            ? $this->applyResizeProfile($image, $profile)
            : $image;

        $encoded = $image->encode($this->encodeFormat($extension), $quality);

        Storage::disk($this->disk())->put($fullPath, (string) $encoded, 'public');

        return $fullPath;
    }

    public function storeFile(UploadedFile $file): string
    {
        $path = $this->basePath();
        $extension = strtolower($file->getClientOriginalExtension());
        $filename = $this->uniqueFilename($file, $path);
        $storedName = $filename.'.'.$extension;

        $file->storeAs($path, $storedName, $this->disk());

        $fullPath = $path.$storedName;

        return json_encode([
            [
                'download_link' => $fullPath,
                'original_name' => $file->getClientOriginalName(),
            ],
        ]);
    }

    public function deleteValue(?string $value, string $type): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $disk = Storage::disk($this->disk());

        if ($type === 'image') {
            if ($disk->exists($value)) {
                $disk->delete($value);
            }

            return;
        }

        if ($type !== 'file') {
            return;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $link = $entry['download_link'] ?? null;
            if (is_string($link) && $link !== '' && $disk->exists($link)) {
                $disk->delete($link);
            }
        }
    }

    public function previewUrl(?string $value, string $type): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($type === 'image') {
            if (! Storage::disk($this->disk())->exists($value)) {
                return null;
            }

            return Voyager::image($value);
        }

        if ($type !== 'file') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (! is_array($decoded) || ! isset($decoded[0]) || ! is_array($decoded[0])) {
            return null;
        }

        $link = $decoded[0]['download_link'] ?? null;
        if (! is_string($link) || $link === '' || ! Storage::disk($this->disk())->exists($link)) {
            return null;
        }

        $ext = strtolower(pathinfo($link, PATHINFO_EXTENSION));
        if (! in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true)) {
            return null;
        }

        return Voyager::image($link);
    }

    public function hasStoredValue(?string $value, string $type): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if ($type === 'image') {
            return Storage::disk($this->disk())->exists($value);
        }

        if ($type !== 'file') {
            return false;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) && $decoded !== [];
    }

    /**
     * @param  array{mode: string, width: int|null, height: int|null, quality: int, upsize: bool}  $profile
     */
    private function applyResizeProfile(InterventionImage $image, array $profile): InterventionImage
    {
        if ($profile['mode'] === 'fit') {
            return $image->fit(
                (int) $profile['width'],
                (int) $profile['height'],
                static function (Constraint $constraint) use ($profile): void {
                    if (! $profile['upsize']) {
                        $constraint->upsize();
                    }
                }
            );
        }

        return $image->resize(
            $profile['width'],
            $profile['height'],
            static function (Constraint $constraint) use ($profile): void {
                $constraint->aspectRatio();
                if (! $profile['upsize']) {
                    $constraint->upsize();
                }
            }
        );
    }

    private function encodeFormat(string $extension): string
    {
        return match ($extension) {
            'jpg', 'jpeg' => 'jpg',
            default => $extension,
        };
    }

    private function basePath(): string
    {
        return 'settings/'.date('FY').'/';
    }

    private function uniqueFilename(UploadedFile $file, string $path): string
    {
        $disk = Storage::disk($this->disk());
        $extension = strtolower($file->getClientOriginalExtension());

        do {
            $filename = Str::random(20);
        } while ($disk->exists($path.$filename.'.'.$extension));

        return $filename;
    }

    private function isAnimatedGif(string $filename): bool
    {
        if ($filename === '' || ! is_readable($filename)) {
            return false;
        }

        $raw = file_get_contents($filename);
        if ($raw === false) {
            return false;
        }

        $offset = 0;
        $frames = 0;

        while ($frames < 2) {
            $where1 = strpos($raw, "\x00\x21\xF9\x04", $offset);
            if ($where1 === false) {
                break;
            }

            $offset = $where1 + 1;
            $where2 = strpos($raw, "\x00\x2C", $offset);
            if ($where2 === false) {
                break;
            }

            if ($where1 + 8 === $where2) {
                $frames++;
            }

            $offset = $where2 + 1;
        }

        return $frames > 1;
    }
}
