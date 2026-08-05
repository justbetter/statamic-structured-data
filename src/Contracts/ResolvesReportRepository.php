<?php

namespace Justbetter\StatamicStructuredData\Contracts;

use Justbetter\StatamicStructuredData\Repositories\ReportRepository;

interface ResolvesReportRepository
{
    public function resolve(): ReportRepository;
}
