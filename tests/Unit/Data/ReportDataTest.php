<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Data;

use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportIssueSeverity;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReportDataTest extends TestCase
{
    #[Test]
    public function it_hydrates_items_and_exposes_helpers(): void
    {
        $report = Report::make([
            'id' => 'report-1',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'items' => [[
                'id' => 'item-1',
                'issue_type' => ReportIssueType::IncompleteField->value,
                'item_type' => ReportItemType::Entry->value,
                'item_id' => 'entry-1',
                'field_path' => 'name',
            ]],
        ]);

        $this->assertSame(ReportStatus::Completed, $report->status());
        $this->assertCount(1, $report->items());
        $this->assertInstanceOf(ReportItem::class, $report->items()->first());
        $this->assertSame(ReportIssueType::IncompleteField, $report->items()->first()->issueType());
        $this->assertSame(ReportItemType::Entry, $report->items()->first()->itemType());
        $this->assertArrayNotHasKey('items', $report->toSummaryArray());
        $this->assertArrayHasKey('items', $report->toArray());
    }

    #[Test]
    public function it_normalizes_invalid_items_and_defaults(): void
    {
        $report = Report::make([
            'id' => 123,
            'site' => ['not-a-string'],
            'status' => ReportStatus::Completed->value,
            'items_scanned' => '5',
            'missing_automatic_template_count' => 1.5,
            'incomplete_field_count' => ['nope'],
            'items' => [
                ReportItem::make([
                    'id' => 'item-1',
                    'issue_type' => ReportIssueType::MissingAutomaticTemplate->value,
                    'item_type' => ReportItemType::Entry->value,
                    'item_id' => 'entry-1',
                ]),
                'invalid',
                42,
            ],
        ]);

        $this->assertCount(3, $report->items());
        $this->assertSame('123', $report->toArray()['id']);
        $this->assertSame('', $report->toArray()['site']);
        $this->assertSame(5, $report->toArray()['items_scanned']);
        $this->assertSame(1, $report->toArray()['missing_automatic_template_count']);
        $this->assertSame(0, $report->toArray()['incomplete_field_count']);

        $withoutItems = Report::make([
            'id' => 'report-2',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
        ]);
        $this->assertCount(0, $withoutItems->items());

        $fluent = new Report([
            'id' => 'report-3',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'items' => 'not-a-collection',
        ]);
        $this->assertCount(0, $fluent->items());
    }

    #[Test]
    public function it_normalizes_metrics_and_invalid_scopes(): void
    {
        $report = Report::make([
            'id' => 'report-metrics',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'missing_automatic_template_count' => 4,
            'coverage_percent' => '87.5',
            'scopes' => 'invalid',
        ]);

        $array = $report->toArray();
        $this->assertSame(4, $array['missing_automatic_template_count']);
        $this->assertSame(87.5, $array['coverage_percent']);
        $this->assertSame([], $array['scopes']);

        $nonArrayScopes = new Report([
            'id' => 'report-scopes',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'scopes' => 'still-invalid',
        ]);
        $this->assertSame([], $nonArrayScopes->toArray()['scopes']);
    }

    #[Test]
    public function it_derives_severity_from_issue_type_and_exposes_is_error(): void
    {
        $item = ReportItem::make([
            'id' => 'item-1',
            'issue_type' => ReportIssueType::NoTemplateAssigned->value,
            'item_type' => ReportItemType::Entry->value,
            'item_id' => 'entry-1',
        ]);

        $this->assertSame(ReportIssueSeverity::Warning, $item->severity());

        $errorItem = new ReportItem([
            'id' => 'item-2',
            'issue_type' => ReportIssueType::MissingAutomaticTemplate->value,
            'severity' => 'not-a-severity',
            'item_type' => ReportItemType::Entry->value,
            'item_id' => 'entry-2',
        ]);
        $this->assertSame(ReportIssueSeverity::Error, $errorItem->severity());

        $this->assertFalse(ReportIssueType::NoTemplateAssigned->isError());
        $this->assertTrue(ReportIssueType::MissingAutomaticTemplate->isError());
        $this->assertTrue(ReportIssueType::IncompleteField->isError());
    }
}
