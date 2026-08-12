<?php

namespace Justbetter\StatamicStructuredData\Services\Report;

final class ReportScanStats
{
    public int $itemsScanned = 0;

    public int $coverageExpected = 0;

    public int $coveragePresent = 0;

    public int $itemsWithTemplate = 0;

    public int $itemsComplete = 0;

    /** @var array<string, true> */
    public array $itemsWithErrors = [];

    /** @var array<string, ReportScopeStats> */
    public array $scopes = [];

    public function scope(string $key, string $scopeType, string $scopeHandle): ReportScopeStats
    {
        if (! isset($this->scopes[$key])) {
            $this->scopes[$key] = new ReportScopeStats($key, $scopeType, $scopeHandle);
        }

        return $this->scopes[$key];
    }
}
