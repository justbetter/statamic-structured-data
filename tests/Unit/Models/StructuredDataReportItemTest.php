<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Models;

use Justbetter\StatamicStructuredData\Models\StructuredDataReport;
use Justbetter\StatamicStructuredData\Models\StructuredDataReportItem;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StructuredDataReportItemTest extends TestCase
{
    #[Test]
    public function it_belongs_to_a_report(): void
    {
        $report = StructuredDataReport::create([
            'id' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'site' => 'default',
            'status' => 'completed',
        ]);

        $item = StructuredDataReportItem::create([
            'id' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'report_id' => $report->id,
            'issue_type' => 'missing_automatic_template',
            'item_type' => 'entry',
            'item_id' => 'entry-1',
        ]);

        $this->assertTrue($item->report()->is($report));
    }
}
