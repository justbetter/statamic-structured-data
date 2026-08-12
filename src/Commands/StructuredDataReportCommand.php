<?php

namespace Justbetter\StatamicStructuredData\Commands;

use Illuminate\Console\Command;
use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Jobs\GenerateStructuredDataReportJob;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Sites\Site;
use Throwable;

class StructuredDataReportCommand extends Command
{
    protected $signature = 'structured-data:report
        {--site= : Site handle (defaults to current/default site)}
        {--template= : Limit the report to a single template ID}
        {--all : Generate a report for all expected templates (default behaviour)}
        {--queue : Dispatch the report generation to the queue}
        {--json : Output the report as JSON}
        {--fail-on-issues : Exit with a non-zero code when errors are found}
        {--fail-on-warnings : Exit with a non-zero code when warnings are found}';

    protected $description = 'Generate a structured data coverage and completeness report';

    public function handle(GeneratesStructuredDataReport $generator): int
    {
        $siteOption = $this->option('site');
        $templateOption = $this->option('template');

        /** @var Site $selectedSite */
        $selectedSite = SiteFacade::selected();
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();

        $site = is_string($siteOption) && $siteOption !== ''
            ? $siteOption
            : $selectedSite->handle();

        if ($site === '') {
            $site = $defaultSite->handle();
        }

        $templateId = is_string($templateOption) && $templateOption !== ''
            ? $templateOption
            : null;

        /** @var array{site: string, template_id?: string|null, triggered_by?: string|null, actor?: string|null} $options */
        $options = [
            'site' => $site,
            'template_id' => $templateId,
            'triggered_by' => 'cli',
            'actor' => 'cli',
        ];

        if ($this->option('queue')) {
            GenerateStructuredDataReportJob::dispatch($options);
            $this->info('Structured data report queued for site ['.$options['site'].'].');

            return self::SUCCESS;
        }

        try {
            $report = $generator->generate($options);
        } catch (Throwable $e) {
            $this->error('Report generation failed: '.$e->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->renderTable($report);
        }

        $errorCount = $this->intFromMixed($report->error_count ?? 0);
        $warningCount = $this->intFromMixed($report->warning_count ?? 0);

        if ($this->option('fail-on-issues') && $errorCount > 0) {
            return self::FAILURE;
        }

        if ($this->option('fail-on-warnings') && $warningCount > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    protected function renderTable(Report $report): void
    {
        $this->info('Structured data report ['.$this->stringFromMixed($report->id).']');
        $this->line('Site: '.$this->stringFromMixed($report->site));
        $this->line('Status: '.$this->stringFromMixed($report->status));
        $this->line('Items scanned: '.$this->stringFromMixed($report->items_scanned));
        $this->line('Coverage: '.$this->stringFromMixed($report->coverage_percent).'%');
        $this->line('Completeness: '.$this->stringFromMixed($report->completeness_percent).'%');
        $this->line('Clean: '.$this->stringFromMixed($report->clean_percent).'%');
        $this->line('Errors: '.$this->stringFromMixed($report->error_count));
        $this->line('Warnings: '.$this->stringFromMixed($report->warning_count));
        $this->newLine();

        if ($report->items()->isEmpty()) {
            $this->info('No issues found.');

            return;
        }

        $this->table(
            ['Severity', 'Issue', 'Item', 'Template', 'Field', 'Scope'],
            $report->items()->map(fn (ReportItem $item): array => [
                $this->stringFromMixed($item->severity),
                $this->stringFromMixed($item->issue_type),
                $this->stringFromMixed($item->item_title ?: $item->item_id),
                $this->stringFromMixed($item->template_title ?: $item->template_id),
                $this->stringFromMixed($item->field_path),
                $this->stringFromMixed($item->scope_type).':'.$this->stringFromMixed($item->scope_handle),
            ])->all(),
        );
    }

    protected function stringFromMixed(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    protected function intFromMixed(mixed $value): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => 0,
        };
    }
}
