<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Upload;

use App\Support\Upload\UploadSizeLimit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UploadSizeLimitTest extends TestCase
{
    public function test_default_platform_ceiling_is_50_megabytes(): void
    {
        $this->assertSame(51200, UploadSizeLimit::maxKilobytes());
        $this->assertSame(51200 * 1024, UploadSizeLimit::maxBytes());
        $this->assertSame('max:51200', UploadSizeLimit::laravelMaxRule());
        $this->assertSame('50 MB', UploadSizeLimit::humanLabel());
    }

    public function test_import_spreadsheet_limit_is_below_platform_ceiling(): void
    {
        $this->assertSame(5120, UploadSizeLimit::importSpreadsheetMaxKilobytes());
        $this->assertLessThan(UploadSizeLimit::maxKilobytes(), UploadSizeLimit::importSpreadsheetMaxKilobytes());
    }

    public function test_settings_limits_are_below_platform_ceiling(): void
    {
        $this->assertLessThan(UploadSizeLimit::maxKilobytes(), UploadSizeLimit::settingsImageMaxKilobytes());
        $this->assertLessThan(UploadSizeLimit::maxKilobytes(), UploadSizeLimit::settingsFileMaxKilobytes());
    }

    public function test_human_label_for_kilobytes(): void
    {
        $this->assertSame('5 MB', UploadSizeLimit::humanLabelForKilobytes(5120));
        $this->assertSame('10 MB', UploadSizeLimit::humanLabelForKilobytes(10240));
    }

    public function test_laravel_rule_rejects_file_above_platform_ceiling(): void
    {
        $oversized = UploadedFile::fake()->create(
            'evidencia.pdf',
            UploadSizeLimit::maxKilobytes() + 1,
            'application/pdf',
        );

        $validator = Validator::make(
            ['archivo' => $oversized],
            ['archivo' => ['file', UploadSizeLimit::laravelMaxRule()]],
        );

        $this->assertTrue($validator->fails());
    }
}
