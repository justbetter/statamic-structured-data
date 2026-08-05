<?php

namespace Justbetter\StatamicStructuredData\Repositories;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Models\StructuredDataReport;
use Justbetter\StatamicStructuredData\Models\StructuredDataReportItem;

class EloquentReportRepository extends ReportRepository
{
    public function store(Report $report): Report
    {
        $model = StructuredDataReport::create($this->reportAttributes($report));

        $this->syncItems($model, $report->items());

        $fresh = $model->fresh(['items']);

        return $this->toData($fresh ?? $model);
    }

    public function update(Report $report): Report
    {
        $model = StructuredDataReport::query()->find($report->id);

        if (! $model) {
            return $this->store($report);
        }

        $model->update($this->reportAttributes($report));
        $this->syncItems($model, $report->items());

        $fresh = $model->fresh(['items']);

        return $this->toData($fresh ?? $model);
    }

    public function find(string $id): ?Report
    {
        $model = StructuredDataReport::query()->with('items')->find($id);

        return $model ? $this->toData($model) : null;
    }

    public function allForSite(string $site): Collection
    {
        return StructuredDataReport::query()
            ->with('items')
            ->where('site', $site)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (StructuredDataReport $model): Report => $this->toData($model))
            ->values();
    }

    public function delete(string $id): void
    {
        StructuredDataReport::query()->where('id', $id)->delete();
    }

    public function pruneOlderThan(int $days): int
    {
        if ($days < 1) {
            return 0;
        }

        $cutoff = Carbon::now()->subDays($days);

        $deleted = StructuredDataReport::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        return is_int($deleted) ? $deleted : 0;
    }

    /**
     * @return array<string, mixed>
     */
    protected function reportAttributes(Report $report): array
    {
        $data = $report->toArray();

        return [
            'id' => $data['id'],
            'site' => $data['site'],
            'status' => $data['status'],
            'triggered_by' => $data['triggered_by'],
            'actor' => $data['actor'],
            'error' => $data['error'],
            'template_id' => $data['template_id'],
            'items_scanned' => $data['items_scanned'],
            'error_count' => $data['error_count'],
            'warning_count' => $data['warning_count'],
            'missing_automatic_template_count' => $data['missing_automatic_template_count'],
            'no_template_assigned_count' => $data['no_template_assigned_count'],
            'incomplete_field_count' => $data['incomplete_field_count'],
            'coverage_expected' => $data['coverage_expected'],
            'coverage_present' => $data['coverage_present'],
            'items_with_template' => $data['items_with_template'],
            'items_complete' => $data['items_complete'],
            'coverage_percent' => $data['coverage_percent'],
            'completeness_percent' => $data['completeness_percent'],
            'clean_percent' => $data['clean_percent'],
            'scopes' => $data['scopes'],
            'created_at' => $data['created_at'],
            'completed_at' => $data['completed_at'],
        ];
    }

    /**
     * @param  Collection<int, ReportItem>  $items
     */
    protected function syncItems(StructuredDataReport $model, Collection $items): void
    {
        $model->items()->delete();

        $rows = $items->map(function (ReportItem $item) use ($model): array {
            $data = $item->toArray();
            $data['id'] = $data['id'] ?? (string) Str::uuid();
            $data['report_id'] = $model->id;

            return $data;
        })->all();

        if ($rows !== []) {
            StructuredDataReportItem::query()->insert($rows);
        }
    }

    protected function toData(StructuredDataReport $model): Report
    {
        return Report::make([
            'id' => $model->id,
            'site' => $model->site,
            'status' => $model->status,
            'triggered_by' => $model->triggered_by,
            'actor' => $model->actor,
            'error' => $model->error,
            'template_id' => $model->template_id,
            'items_scanned' => $model->items_scanned,
            'error_count' => $model->error_count,
            'warning_count' => $model->warning_count,
            'missing_automatic_template_count' => $model->missing_automatic_template_count,
            'no_template_assigned_count' => $model->no_template_assigned_count,
            'incomplete_field_count' => $model->incomplete_field_count,
            'coverage_expected' => $model->coverage_expected,
            'coverage_present' => $model->coverage_present,
            'items_with_template' => $model->items_with_template,
            'items_complete' => $model->items_complete,
            'coverage_percent' => $model->coverage_percent,
            'completeness_percent' => $model->completeness_percent,
            'clean_percent' => $model->clean_percent,
            'scopes' => $model->scopes ?? [],
            'created_at' => $model->created_at?->toIso8601String(),
            'completed_at' => $model->completed_at?->toIso8601String(),
            'items' => $model->items->map(fn (StructuredDataReportItem $item): array => [
                'id' => $item->id,
                'issue_type' => $item->issue_type,
                'severity' => $item->severity ?? null,
                'item_type' => $item->item_type,
                'item_id' => $item->item_id,
                'item_title' => $item->item_title,
                'item_edit_url' => $item->item_edit_url,
                'item_url' => $item->item_url,
                'template_id' => $item->template_id,
                'template_title' => $item->template_title,
                'schema_type' => $item->schema_type,
                'field_path' => $item->field_path,
                'scope_handle' => $item->scope_handle,
                'scope_type' => $item->scope_type,
            ])->all(),
        ]);
    }
}
