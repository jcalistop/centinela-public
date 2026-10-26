<?php

namespace Tests\Unit\Support\Cen;

use App\Models\CenCenterSyncRunIssue;
use App\Support\Cen\CenEstablishmentsUiCopy;
use PHPUnit\Framework\TestCase;

class CenEstablishmentsUiCopyTest extends TestCase
{
    public function test_issue_code_label_humanizes_unknown_codes(): void
    {
        $this->assertSame(
            'Texto de catálogo sin equivalencia',
            CenEstablishmentsUiCopy::issueCodeLabel('unmapped_enum'),
        );
    }

    public function test_issue_message_for_unmapped_enum_uses_field_label(): void
    {
        $issue = new CenCenterSyncRunIssue([
            'code' => 'unmapped_enum',
            'message' => 'Glosa no mapeada para estado_funcionamiento: Foo',
            'context' => [
                'field' => 'estado_funcionamiento',
                'api_glosa' => 'Foo',
            ],
        ]);

        $message = CenEstablishmentsUiCopy::issueMessageForDisplay($issue, [
            'estado_funcionamiento' => 'Estado de funcionamiento',
        ]);

        $this->assertStringContainsString('Estado de funcionamiento', $message);
        $this->assertStringContainsString('Foo', $message);
        $this->assertStringNotContainsString('unmapped_enum', $message);
    }
}
