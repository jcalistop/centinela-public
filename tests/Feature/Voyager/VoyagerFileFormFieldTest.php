<?php

namespace Tests\Feature\Voyager;

use App\Support\Upload\UploadSizeLimit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class VoyagerFileFormFieldTest extends TestCase
{
    use RefreshDatabase;

    public function test_file_blade_does_not_use_loop_last_for_remove_single_file(): void
    {
        $blade = File::get(resource_path('views/vendor/voyager/formfields/file.blade.php'));

        $this->assertStringNotContainsString('$loop->last', $blade);
        $this->assertStringNotContainsString('remove-single-file QUE APUNTA', $blade);
        $this->assertStringContainsString('remove-multi-file', $blade);
    }

    public function test_single_json_file_uses_remove_multi_file_class(): void
    {
        $html = $this->renderFileFormField([
            [
                'download_link' => 'medios-verificacion/doc.pdf',
                'original_name' => 'doc.pdf',
            ],
        ]);

        $this->assertStringContainsString('remove-multi-file', $html);
        $this->assertStringNotContainsString('remove-single-file', $html);
        $this->assertStringContainsString('data-file-name="doc.pdf"', $html);
    }

    public function test_multiple_json_files_all_use_remove_multi_file_class(): void
    {
        $html = $this->renderFileFormField([
            [
                'download_link' => 'medios-verificacion/a.pdf',
                'original_name' => 'a.pdf',
            ],
            [
                'download_link' => 'medios-verificacion/b.pdf',
                'original_name' => 'b.pdf',
            ],
        ]);

        $this->assertSame(2, substr_count($html, 'remove-multi-file'));
        $this->assertStringNotContainsString('remove-single-file', $html);
        $this->assertStringContainsString('/attachments/42/0', $html);
        $this->assertStringContainsString('/attachments/42/1', $html);
    }

    public function test_legacy_plain_path_uses_remove_single_file_class(): void
    {
        $html = $this->renderFileFormField('medios-verificacion/legacy.pdf');

        $this->assertStringContainsString('remove-single-file', $html);
        $this->assertStringNotContainsString('remove-multi-file', $html);
        $this->assertStringContainsString('data-file-name="medios-verificacion/legacy.pdf"', $html);
    }

    public function test_file_input_keeps_upload_size_limits_and_hint(): void
    {
        $html = $this->renderFileFormField([
            [
                'download_link' => 'medios-verificacion/doc.pdf',
                'original_name' => 'doc.pdf',
            ],
        ]);

        $this->assertStringContainsString('data-max-upload-bytes="'.UploadSizeLimit::maxBytes().'"', $html);
        $this->assertStringContainsString('data-max-upload-label="'.UploadSizeLimit::humanLabel().'"', $html);
        $this->assertStringContainsString('name="attachments[]"', $html);
        $this->assertStringContainsString('multiple="multiple"', $html);
    }

    public function test_required_attribute_when_field_is_required_and_empty(): void
    {
        $html = $this->renderFileFormField(null, required: true);

        $this->assertStringContainsString('required', $html);
    }

    /**
     * @param  array<int, array<string, string>>|string|null  $fieldValue
     */
    private function renderFileFormField(array|string|null $fieldValue, bool $required = false): string
    {
        $row = new \stdClass;
        $row->field = 'attachments';
        $row->required = $required ? 1 : 0;

        $dataType = new \stdClass;
        $dataType->slug = 'medios-verificacion';

        $dataTypeContent = new class
        {
            public int $id = 42;

            public ?string $attachments = null;

            public function getKey(): int
            {
                return $this->id;
            }
        };

        if (is_array($fieldValue)) {
            $dataTypeContent->attachments = json_encode($fieldValue);
        } elseif (is_string($fieldValue)) {
            $dataTypeContent->attachments = $fieldValue;
        }

        return View::make('voyager::formfields.file', [
            'row' => $row,
            'options' => [],
            'dataType' => $dataType,
            'dataTypeContent' => $dataTypeContent,
        ])->render();
    }
}
