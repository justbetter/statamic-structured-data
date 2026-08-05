<?php

namespace Justbetter\StatamicStructuredData\Data;

use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;

/**
 * @extends Fluent<string, mixed>
 *
 * @property string $id
 * @property string $site
 * @property string $status
 * @property string|null $triggered_by
 * @property string|null $actor
 * @property string|null $error
 * @property string|null $template_id
 * @property int $items_scanned
 * @property int $error_count
 * @property int $warning_count
 * @property int $missing_automatic_template_count
 * @property int $no_template_assigned_count
 * @property int $incomplete_field_count
 * @property int $coverage_expected
 * @property int $coverage_present
 * @property int $items_with_template
 * @property int $items_complete
 * @property float|int $coverage_percent
 * @property float|int $completeness_percent
 * @property float|int $clean_percent
 * @property array<int, array<string, mixed>> $scopes
 * @property string|null $created_at
 * @property string|null $completed_at
 * @property Collection<int, ReportItem>|array<int, array<string, mixed>> $items
 */
final class Report extends Fluent
{
    public static function make($attributes = []): static
    {
        /** @var array<string, mixed> $data */
        $data = is_array($attributes) ? $attributes : [];

        if (isset($data['items']) && is_array($data['items'])) {
            $data['items'] = collect($data['items'])
                ->map(function (mixed $item): ReportItem {
                    if ($item instanceof ReportItem) {
                        return $item;
                    }

                    if (! is_array($item)) {
                        return ReportItem::make([]);
                    }

                    $normalized = [];
                    foreach ($item as $key => $value) {
                        if (is_string($key)) {
                            $normalized[$key] = $value;
                        }
                    }

                    return ReportItem::make($normalized);
                })
                ->values();
        }

        if (! isset($data['items'])) {
            $data['items'] = collect();
        }

        if (! isset($data['scopes']) || ! is_array($data['scopes'])) {
            $data['scopes'] = [];
        }

        return new self($data);
    }

    public function status(): ReportStatus
    {
        return ReportStatus::from($this->stringValue($this['status'] ?? null));
    }

    /**
     * @return Collection<int, ReportItem>
     */
    public function items(): Collection
    {
        $items = $this['items'] ?? null;

        if ($items instanceof Collection) {
            /** @var Collection<int, ReportItem> $items */
            return $items;
        }

        return collect();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->stringValue($this->id),
            'site' => $this->stringValue($this->site),
            'status' => $this->stringValue($this->status),
            'triggered_by' => $this->nullableString($this->triggered_by),
            'actor' => $this->nullableString($this->actor),
            'error' => $this->nullableString($this->error),
            'template_id' => $this->nullableString($this->template_id),
            'items_scanned' => $this->intValue($this->items_scanned ?? 0),
            'error_count' => $this->intValue($this->error_count ?? 0),
            'warning_count' => $this->intValue($this->warning_count ?? 0),
            'missing_automatic_template_count' => $this->intValue($this->missing_automatic_template_count ?? 0),
            'no_template_assigned_count' => $this->intValue($this->no_template_assigned_count ?? 0),
            'incomplete_field_count' => $this->intValue($this->incomplete_field_count ?? 0),
            'coverage_expected' => $this->intValue($this->coverage_expected ?? 0),
            'coverage_present' => $this->intValue($this->coverage_present ?? 0),
            'items_with_template' => $this->intValue($this->items_with_template ?? 0),
            'items_complete' => $this->intValue($this->items_complete ?? 0),
            'coverage_percent' => $this->floatValue($this->coverage_percent ?? 100),
            'completeness_percent' => $this->floatValue($this->completeness_percent ?? 100),
            'clean_percent' => $this->floatValue($this->clean_percent ?? 100),
            'scopes' => $this->scopesArray(),
            'created_at' => $this->nullableString($this->created_at),
            'completed_at' => $this->nullableString($this->completed_at),
            'items' => $this->items()->map(fn (ReportItem $item): array => $item->toArray())->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryArray(): array
    {
        $data = $this->toArray();
        unset($data['items']);

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function scopesArray(): array
    {
        $scopes = $this->attributes['scopes'] ?? [];

        if (! is_array($scopes)) {
            return [];
        }

        $normalized = [];
        foreach ($scopes as $scope) {
            if (! is_array($scope)) {
                continue;
            }

            $item = [];
            foreach ($scope as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $item[$key] = $value;
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    protected function stringValue(mixed $value): string
    {
        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    protected function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    protected function intValue(mixed $value, int $default = 0): int
    {
        return match (true) {
            is_int($value) => $value,
            is_float($value) => (int) $value,
            is_string($value) && is_numeric($value) => (int) $value,
            default => $default,
        };
    }

    protected function floatValue(mixed $value, float $default = 0.0): float
    {
        return match (true) {
            is_float($value) => $value,
            is_int($value) => (float) $value,
            is_string($value) && is_numeric($value) => (float) $value,
            default => $default,
        };
    }
}
