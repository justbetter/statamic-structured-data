<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Repositories;

use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Repositories\EloquentReportRepository;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class EloquentReportRepositoryTest extends TestCase
{
    private EloquentReportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = new EloquentReportRepository;
    }

    #[Test]
    public function it_stores_finds_updates_and_lists_reports(): void
    {
        $report = Report::make([
            'id' => '11111111-1111-1111-1111-111111111111',
            'site' => 'default',
            'status' => ReportStatus::Running->value,
            'triggered_by' => 'test',
            'actor' => 'phpunit',
            'items_scanned' => 0,
            'missing_automatic_template_count' => 0,
            'incomplete_field_count' => 0,
            'created_at' => now()->toIso8601String(),
            'items' => [
                ReportItem::make([
                    'id' => '22222222-2222-2222-2222-222222222222',
                    'issue_type' => ReportIssueType::MissingAutomaticTemplate->value,
                    'item_type' => ReportItemType::Entry->value,
                    'item_id' => 'entry-1',
                    'item_title' => 'Entry',
                    'scope_handle' => 'pages',
                    'scope_type' => 'collection',
                ]),
            ],
        ]);

        $stored = $this->repository->store($report);
        $this->assertSame('default', $stored->get('site'));
        $this->assertCount(1, $stored->items());

        $found = $this->repository->find('11111111-1111-1111-1111-111111111111');
        $this->assertNotNull($found);

        $updated = Report::make([
            ...$found->toArray(),
            'status' => ReportStatus::Completed->value,
            'items_scanned' => 3,
            'missing_automatic_template_count' => 1,
            'items' => $found->items()->all(),
        ]);

        $this->repository->update($updated);

        $refreshed = $this->repository->find('11111111-1111-1111-1111-111111111111');
        $this->assertNotNull($refreshed);
        $this->assertSame(ReportStatus::Completed->value, $refreshed->get('status'));
        $this->assertSame(3, $refreshed->get('items_scanned'));

        $this->assertCount(1, $this->repository->allForSite('default'));
        $this->assertCount(0, $this->repository->allForSite('en'));

        $this->repository->delete('11111111-1111-1111-1111-111111111111');
        $this->assertNull($this->repository->find('11111111-1111-1111-1111-111111111111'));
    }

    #[Test]
    public function it_prunes_old_reports(): void
    {
        $old = Report::make([
            'id' => '33333333-3333-3333-3333-333333333333',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->subDays(120)->toIso8601String(),
            'items' => [],
        ]);
        $fresh = Report::make([
            'id' => '44444444-4444-4444-4444-444444444444',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->toIso8601String(),
            'items' => [],
        ]);

        $this->repository->store($old);
        $this->repository->store($fresh);

        $deleted = $this->repository->pruneOlderThan(90);

        $this->assertSame(1, $deleted);
        $this->assertNull($this->repository->find('33333333-3333-3333-3333-333333333333'));
        $this->assertNotNull($this->repository->find('44444444-4444-4444-4444-444444444444'));
    }

    #[Test]
    public function it_stores_when_updating_a_missing_report(): void
    {
        $report = Report::make([
            'id' => '55555555-5555-5555-5555-555555555555',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->toIso8601String(),
            'items' => [],
        ]);

        $stored = $this->repository->update($report);

        $this->assertSame('55555555-5555-5555-5555-555555555555', $stored->get('id'));
        $this->assertNotNull($this->repository->find('55555555-5555-5555-5555-555555555555'));
    }

    #[Test]
    public function it_does_not_prune_when_days_are_below_one(): void
    {
        $this->assertSame(0, $this->repository->pruneOlderThan(0));
    }
}
