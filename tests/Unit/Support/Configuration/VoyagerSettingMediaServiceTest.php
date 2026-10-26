<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Configuration;

use App\Support\Configuration\VoyagerSettingMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VoyagerSettingMediaServiceTest extends TestCase
{
    public function test_store_image_writes_under_settings_folder(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('logo.png', 80, 80);

        $path = $service->storeImage($file);

        $this->assertStringStartsWith('settings/', $path);
        $this->assertTrue(Storage::disk('private')->exists($path));
    }

    public function test_delete_value_removes_image_from_disk(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('icon.png', 40, 40);
        $path = $service->storeImage($file);

        $service->deleteValue($path, 'image');

        $this->assertFalse(Storage::disk('private')->exists($path));
    }

    public function test_store_image_icon_downscales_to_profile_dimensions(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('icon.png', 800, 600);

        $path = $service->storeImage($file, 'admin.icon_image');
        $dimensions = $this->storedImageDimensions('private', $path);

        $this->assertSame(128, $dimensions[0]);
        $this->assertSame(128, $dimensions[1]);
    }

    public function test_store_image_bg_downscales_width_preserving_aspect_ratio(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('bg.jpg', 3000, 900);

        $path = $service->storeImage($file, 'admin.bg_image');
        $dimensions = $this->storedImageDimensions('private', $path);

        $this->assertSame(1920, $dimensions[0]);
        $this->assertSame(576, $dimensions[1]);
    }

    public function test_store_image_without_profile_keeps_original_dimensions(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('other.png', 400, 300);

        $path = $service->storeImage($file, 'admin.unknown_image');
        $dimensions = $this->storedImageDimensions('private', $path);

        $this->assertSame(400, $dimensions[0]);
        $this->assertSame(300, $dimensions[1]);
    }

    public function test_resize_profile_for_returns_null_for_unknown_key(): void
    {
        $service = new VoyagerSettingMediaService;

        $this->assertNull($service->resizeProfileFor('admin.not_configured'));
    }

    public function test_resize_profile_for_returns_icon_profile(): void
    {
        $service = new VoyagerSettingMediaService;

        $profile = $service->resizeProfileFor('admin.icon_image');

        $this->assertNotNull($profile);
        $this->assertSame('fit', $profile['mode']);
        $this->assertSame(128, $profile['width']);
        $this->assertSame(128, $profile['height']);
        $this->assertFalse($profile['upsize']);
    }

    public function test_store_image_icon_does_not_upsize_small_upload(): void
    {
        Storage::fake('private');

        $service = new VoyagerSettingMediaService;
        $file = UploadedFile::fake()->image('tiny.png', 32, 32);

        $path = $service->storeImage($file, 'admin.icon_image');
        $dimensions = $this->storedImageDimensions('private', $path);

        $this->assertSame(32, $dimensions[0]);
        $this->assertSame(32, $dimensions[1]);
    }

    public function test_animated_gif_is_stored_without_resize_even_with_profile(): void
    {
        Storage::fake('private');

        $gifBytes = base64_decode(
            'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'.
            'R0lGODlhAgACAIAAAAAAAP///yH5BAEAAAAALAAAAAACAAIAAwAEEBAQAA==',
            true
        );
        $this->assertNotFalse($gifBytes);

        $tempPath = tempnam(sys_get_temp_dir(), 'loader');
        $this->assertNotFalse($tempPath);
        file_put_contents($tempPath, $gifBytes);

        $service = new VoyagerSettingMediaService;
        $file = new UploadedFile($tempPath, 'loader.gif', 'image/gif', null, true);
        $stored = $service->storeImage($file, 'admin.loader');

        @unlink($tempPath);

        $this->assertTrue(Storage::disk('private')->exists($stored));
        $this->assertGreaterThan(20, Storage::disk('private')->size($stored));
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string, mime: string}
     */
    private function storedImageDimensions(string $disk, string $path): array
    {
        $bytes = Storage::disk($disk)->get($path);
        $this->assertNotFalse($bytes);

        $dimensions = getimagesizefromstring($bytes);
        $this->assertIsArray($dimensions);

        return $dimensions;
    }
}
