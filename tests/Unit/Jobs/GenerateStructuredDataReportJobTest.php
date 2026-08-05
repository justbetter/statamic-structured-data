<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Jobs;

use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Jobs\GenerateStructuredDataReportJob;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class GenerateStructuredDataReportJobTest extends TestCase
{
    #[Test]
    public function it_sets_queue_from_config_and_generates_report(): void
    {
        config()->set('justbetter.structured-data.reports.queue', 'reports');

        $options = [
            'site' => 'default',
            'triggered_by' => 'test',
        ];

        $generator = $this->mock(GeneratesStructuredDataReport::class);
        $generator->shouldReceive('generate')
            ->once()
            ->with($options)
            ->andReturn(Report::make([
                'id' => 'report-1',
                'site' => 'default',
                'status' => ReportStatus::Completed->value,
                'items' => [],
            ]));

        $job = new GenerateStructuredDataReportJob($options);

        $this->assertSame('reports', $job->queue);

        /** @var GeneratesStructuredDataReport $generator */
        $job->handle($generator);
    }
}
