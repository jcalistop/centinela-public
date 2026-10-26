<?php

namespace Tests\Unit\Support\Metas;

use App\Enums\MetaOperator;
use App\Support\Metas\MetIndicatorMetaPresenter;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MetIndicatorMetaPresenterTest extends TestCase
{
    #[Test]
    public function plain_label_formats_operator_value_and_percent_unit(): void
    {
        $this->assertSame(
            '>= 50.00%',
            MetIndicatorMetaPresenter::plainLabel(50, MetaOperator::MayorIgual, '%'),
        );
    }

    #[Test]
    public function plain_label_formats_non_percent_unit_with_space(): void
    {
        $this->assertSame(
            '<= 12.50 días',
            MetIndicatorMetaPresenter::plainLabel(12.5, '<=', 'días'),
        );
    }

    #[Test]
    public function plain_label_omits_unit_when_empty(): void
    {
        $this->assertSame(
            '> 10.00',
            MetIndicatorMetaPresenter::plainLabel(10, '>', null),
        );
    }

    #[Test]
    public function format_compact_number_trims_trailing_zeros(): void
    {
        $this->assertSame('10', MetIndicatorMetaPresenter::formatCompactNumber(10.0));
        $this->assertSame('12.5', MetIndicatorMetaPresenter::formatCompactNumber(12.5));
        $this->assertSame('12.55', MetIndicatorMetaPresenter::formatCompactNumber(12.55));
        $this->assertSame('0', MetIndicatorMetaPresenter::formatCompactNumber(0.0));
    }
}
