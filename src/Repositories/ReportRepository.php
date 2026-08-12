<?php

namespace Justbetter\StatamicStructuredData\Repositories;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Data\Report;

abstract class ReportRepository
{
    abstract public function store(Report $report): Report;

    abstract public function update(Report $report): Report;

    abstract public function find(string $id): ?Report;

    /**
     * @return Collection<int, Report>
     */
    abstract public function allForSite(string $site): Collection;

    abstract public function delete(string $id): void;

    abstract public function pruneOlderThan(int $days): int;
}
