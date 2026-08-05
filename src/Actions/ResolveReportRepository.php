<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Justbetter\StatamicStructuredData\Contracts\ResolvesReportRepository;
use Justbetter\StatamicStructuredData\Exceptions\DriverNotFound;
use Justbetter\StatamicStructuredData\Repositories\FileReportRepository;
use Justbetter\StatamicStructuredData\Repositories\ReportRepository;

class ResolveReportRepository implements ResolvesReportRepository
{
    public function resolve(): ReportRepository
    {
        $driver = config()->string('justbetter.structured-data.reports.driver', 'file');

        /** @var array<string, class-string<ReportRepository>> $drivers */
        $drivers = config()->array('justbetter.structured-data.reports.drivers', []);

        if (! array_key_exists($driver, $drivers)) {
            throw new DriverNotFound('Invalid structured data report driver: '.$driver);
        }

        $class = $drivers[$driver];

        if ($class === FileReportRepository::class) {
            return new FileReportRepository(
                config()->string('justbetter.structured-data.reports.path', base_path('content/structured-data-reports'))
            );
        }

        return app($class);
    }

    public static function bind(): void
    {
        app()->singleton(ResolvesReportRepository::class, static::class);
    }
}
