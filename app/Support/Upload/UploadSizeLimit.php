<?php

namespace App\Support\Upload;

final class UploadSizeLimit
{
    public const IMPORT_SPREADSHEET_MAX_KB = 5120;

    public const SETTINGS_IMAGE_MAX_KB = 5120;

    public const SETTINGS_FILE_MAX_KB = 10240;

    public static function maxKilobytes(): int
    {
        return max(1, (int) config('centinela.upload.max_kilobytes', 25600));
    }

    public static function maxBytes(): int
    {
        return self::maxKilobytes() * 1024;
    }

    public static function laravelMaxRule(): string
    {
        return 'max:'.self::maxKilobytes();
    }

    public static function laravelMaxRuleFor(int $kilobytes): string
    {
        return 'max:'.max(1, $kilobytes);
    }

    public static function humanLabel(): string
    {
        return self::humanLabelForKilobytes(self::maxKilobytes());
    }

    public static function humanLabelForKilobytes(int $kilobytes): string
    {
        if ($kilobytes % 1024 === 0) {
            return ($kilobytes / 1024).' MB';
        }

        return $kilobytes.' KB';
    }

    public static function importSpreadsheetMaxKilobytes(): int
    {
        return self::IMPORT_SPREADSHEET_MAX_KB;
    }

    public static function settingsImageMaxKilobytes(): int
    {
        return self::SETTINGS_IMAGE_MAX_KB;
    }

    public static function settingsFileMaxKilobytes(): int
    {
        return self::SETTINGS_FILE_MAX_KB;
    }

    public static function assertWithinPlatformCeiling(int $kilobytes): void
    {
        if ($kilobytes > self::maxKilobytes()) {
            throw new \InvalidArgumentException(
                "Upload limit {$kilobytes} KB exceeds platform ceiling ".self::maxKilobytes().' KB.'
            );
        }
    }
}
