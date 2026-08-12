<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Repositories;

use Illuminate\Support\Facades\File;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Repositories\FileReportRepository;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileReportRepositoryTest extends TestCase
{
    private string $path;

    private FileReportRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/structured-data-reports-'.uniqid());
        File::deleteDirectory($this->path);
        $this->repository = new FileReportRepository($this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    #[Test]
    public function it_stores_finds_and_lists_reports_per_site(): void
    {
        $report = Report::make([
            'id' => 'report-1',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'items_scanned' => 1,
            'missing_automatic_template_count' => 1,
            'incomplete_field_count' => 0,
            'created_at' => now()->toIso8601String(),
            'items' => [
                ReportItem::make([
                    'id' => 'item-1',
                    'issue_type' => ReportIssueType::MissingAutomaticTemplate->value,
                    'item_type' => ReportItemType::Entry->value,
                    'item_id' => 'entry-1',
                    'item_title' => 'Entry',
                    'scope_handle' => 'pages',
                    'scope_type' => 'collection',
                ]),
            ],
        ]);

        $this->repository->store($report);

        $found = $this->repository->find('report-1');
        $this->assertNotNull($found);
        $this->assertSame('default', $found->get('site'));
        $this->assertCount(1, $found->items());

        $other = Report::make([
            'id' => 'report-2',
            'site' => 'en',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->toIso8601String(),
            'items' => [],
        ]);
        $this->repository->store($other);

        $this->assertCount(1, $this->repository->allForSite('default'));
        $this->assertCount(1, $this->repository->allForSite('en'));
    }

    #[Test]
    public function it_updates_and_deletes_reports(): void
    {
        $report = Report::make([
            'id' => 'report-1',
            'site' => 'default',
            'status' => ReportStatus::Running->value,
            'created_at' => now()->toIso8601String(),
            'items' => [],
        ]);

        $this->repository->store($report);

        $updated = Report::make([
            ...$report->toArray(),
            'status' => ReportStatus::Completed->value,
            'items_scanned' => 5,
        ]);

        $this->repository->update($updated);

        $found = $this->repository->find('report-1');
        $this->assertNotNull($found);
        $this->assertSame(ReportStatus::Completed->value, $found->get('status'));
        $this->assertSame(5, $found->get('items_scanned'));

        $this->repository->delete('report-1');
        $this->assertNull($this->repository->find('report-1'));
    }

    #[Test]
    public function it_prunes_old_reports(): void
    {
        $old = Report::make([
            'id' => 'old',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->subDays(100)->toIso8601String(),
            'items' => [],
        ]);
        $fresh = Report::make([
            'id' => 'fresh',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'created_at' => now()->toIso8601String(),
            'items' => [],
        ]);

        $this->repository->store($old);
        $this->repository->store($fresh);

        $deleted = $this->repository->pruneOlderThan(90);

        $this->assertSame(1, $deleted);
        $this->assertNull($this->repository->find('old'));
        $this->assertNotNull($this->repository->find('fresh'));
    }

    #[Test]
    public function it_returns_empty_collection_when_path_is_not_a_directory(): void
    {
        File::deleteDirectory($this->path);
        File::put($this->path, 'not-a-directory');

        $this->assertCount(0, $this->repository->allForSite('default'));
        $this->assertSame(0, $this->repository->pruneOlderThan(90));

        File::delete($this->path);
    }

    #[Test]
    public function it_skips_non_yaml_files_and_invalid_created_at_when_pruning(): void
    {
        File::put($this->path.'/notes.txt', 'ignore me');
        File::put($this->path.'/bad.yaml', "site: default\nstatus: completed\ncreated_at: 123\n");

        $this->assertSame(0, $this->repository->pruneOlderThan(90));
        $this->assertSame(0, $this->repository->pruneOlderThan(0));
    }
}
