<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Voyager;

use App\Support\Upload\UploadSizeLimit;
use App\Support\Voyager\CentinelaValidatesBreadUploads;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CentinelaValidatesBreadUploadsTest extends TestCase
{
    public function test_injects_max_rule_for_file_rows_without_validation(): void
    {
        $trait = new class
        {
            use CentinelaValidatesBreadUploads {
                injectUploadMaxRulesOnRows as public;
            }
        };

        $row = (object) [
            'type' => 'file',
            'field' => 'archivos',
            'details' => (object) [],
        ];

        $trait->injectUploadMaxRulesOnRows(Collection::make([$row]));

        $this->assertStringContainsString(
            UploadSizeLimit::laravelMaxRule(),
            (string) $row->details->validation->rule,
        );
    }
}
