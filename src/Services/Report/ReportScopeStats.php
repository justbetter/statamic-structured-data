<?php

namespace Justbetter\StatamicStructuredData\Services\Report;

final class ReportScopeStats
{
    public int $itemsScanned = 0;

    public int $coverageExpected = 0;

    public int $coveragePresent = 0;

    public int $itemsWithTemplate = 0;

    public int $itemsComplete = 0;

    /** @var array<string, true> */
    public array $itemsWithErrors = [];

    public function __construct(
        public string $key,
        public string $scopeType,
        public string $scopeHandle,
    ) {}
}
