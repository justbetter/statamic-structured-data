<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Actions;

use Justbetter\StatamicStructuredData\Actions\ResolveReportRepository;
use Justbetter\StatamicStructuredData\Contracts\ResolvesReportRepository;
use Justbetter\StatamicStructuredData\Exceptions\DriverNotFound;
use Justbetter\StatamicStructuredData\Repositories\EloquentReportRepository;
use Justbetter\StatamicStructuredData\Repositories\FileReportRepository;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ResolveReportRepositoryTest extends TestCase
{
    #[Test]
    public function it_resolves_file_driver_by_default(): void
    {
        config()->set('justbetter.structured-data.reports.driver', 'file');
        config()->set('justbetter.structured-data.reports.path', storage_path('framework/testing/reports'));

        $repository = (new ResolveReportRepository)->resolve();

        $this->assertInstanceOf(FileReportRepository::class, $repository);
    }

    #[Test]
    public function it_resolves_eloquent_driver(): void
    {
        config()->set('justbetter.structured-data.reports.driver', 'eloquent');

        $repository = (new ResolveReportRepository)->resolve();

        $this->assertInstanceOf(EloquentReportRepository::class, $repository);
    }

    #[Test]
    public function it_throws_for_unknown_driver(): void
    {
        config()->set('justbetter.structured-data.reports.driver', 'nope');

        $this->expectException(DriverNotFound::class);

        (new ResolveReportRepository)->resolve();
    }

    #[Test]
    public function it_can_bind_itself(): void
    {
        ResolveReportRepository::bind();

        $this->assertInstanceOf(
            ResolveReportRepository::class,
            app(ResolvesReportRepository::class)
        );
    }
}
