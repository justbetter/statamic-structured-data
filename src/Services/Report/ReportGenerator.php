<?php

namespace Justbetter\StatamicStructuredData\Services\Report;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;
use Justbetter\StatamicStructuredData\Contracts\ResolvesReportRepository;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Repositories\ReportRepository;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Entries\Collection as EntryCollection;
use Statamic\Entries\Entry;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Fields\LabeledValue;
use Statamic\Query\EloquentQueryBuilder;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;
use StatamicRadPack\Runway\Resource;
use Throwable;

class ReportGenerator implements GeneratesStructuredDataReport
{
    public function __construct(
        protected ResolvesReportRepository $repositories,
        protected StructuredDataService $structuredData,
        protected CompletenessChecker $completenessChecker,
    ) {}

    public static function bind(): void
    {
        app()->singleton(GeneratesStructuredDataReport::class, static::class);
    }

    /**
     * @param  array{site: string, template_id?: string|null, triggered_by?: string|null, actor?: string|null}  $options
     */
    public function generate(array $options): Report
    {
        $site = $options['site'];
        $templateId = $options['template_id'] ?? null;
        $now = now()->toIso8601String();

        $report = Report::make([
            'id' => (string) Str::uuid(),
            'site' => $site,
            'status' => ReportStatus::Running->value,
            'triggered_by' => $options['triggered_by'] ?? null,
            'actor' => $options['actor'] ?? null,
            'template_id' => $templateId,
            'items_scanned' => 0,
            'error_count' => 0,
            'warning_count' => 0,
            'created_at' => $now,
            'completed_at' => null,
            'items' => [],
            'scopes' => [],
        ]);

        $repository = $this->repositories->resolve();
        $repository->store($report);

        try {
            $templates = $this->publishedTemplates($site, $templateId);
            $items = collect();
            $stats = new ReportScanStats;

            $this->scanCollections($templates, $site, $items, $stats);
            $this->scanTaxonomies($templates, $site, $items, $stats);
            $this->scanRunway($templates, $items, $stats);

            $summary = $this->summarize($items, $stats);

            $report = Report::make([
                ...$report->toArray(),
                'status' => ReportStatus::Completed->value,
                ...$summary,
                'completed_at' => now()->toIso8601String(),
                'items' => $items->values()->all(),
            ]);

            $repository->update($report);
            $this->prune($repository);

            return $report;
        } catch (Throwable $e) {
            $failed = Report::make([
                ...$report->toArray(),
                'status' => ReportStatus::Failed->value,
                'error' => $e->getMessage(),
                'completed_at' => now()->toIso8601String(),
            ]);

            $repository->update($failed);

            throw $e;
        }
    }

    /**
     * @return Collection<int, Entry>
     */
    protected function publishedTemplates(string $site, ?string $templateId = null): Collection
    {
        /** @var EloquentQueryBuilder $query */
        $query = EntryFacade::query();

        $query
            ->where('collection', 'structured_data_templates')
            ->whereStatus('published')
            ->where('site', $site);

        if ($templateId) {
            $query->where('id', $templateId);
        }

        return $query->get()->values();
    }

    /**
     * @param  Collection<int, Entry>  $templates
     * @param  Collection<int, ReportItem>  $items
     */
    protected function scanCollections(Collection $templates, string $site, Collection $items, ReportScanStats $stats): void
    {
        $enabled = config()->array('justbetter.structured-data.collections', []);

        $byCollection = $templates
            ->filter(fn (Entry $template): bool => $this->stringField($template->blueprint_type) === 'collection')
            ->groupBy(fn (Entry $template): string => $this->scopeHandleFromField($template->use_for_collection));

        foreach ($byCollection as $collectionHandle => $scopeTemplates) {
            if ($collectionHandle === '' || ! in_array($collectionHandle, $enabled, true)) {
                continue;
            }

            /** @var Collection<int, Entry> $scopeTemplates */
            $scopeKey = 'collection:'.$collectionHandle;
            $stats->scope($scopeKey, 'collection', (string) $collectionHandle);

            /** @var EloquentQueryBuilder $query */
            $query = EntryFacade::query();

            $entries = $query
                ->where('collection', $collectionHandle)
                ->where('site', $site)
                ->whereStatus('published')
                ->get();

            foreach ($entries as $entry) {
                if (! $entry instanceof Entry) {
                    continue;
                }

                $this->evaluateContentItem(
                    $items,
                    $stats,
                    $entry,
                    $scopeTemplates,
                    ReportItemType::Entry,
                    'collection',
                    (string) $collectionHandle,
                    $scopeKey,
                );
            }
        }
    }

    /**
     * @param  Collection<int, Entry>  $templates
     * @param  Collection<int, ReportItem>  $items
     */
    protected function scanTaxonomies(Collection $templates, string $site, Collection $items, ReportScanStats $stats): void
    {
        $enabled = config()->array('justbetter.structured-data.taxonomies', []);

        $byTaxonomy = $templates
            ->filter(fn (Entry $template): bool => $this->stringField($template->blueprint_type) === 'taxonomy')
            ->groupBy(fn (Entry $template): string => $this->scopeHandleFromField($template->use_for_taxonomy));

        foreach ($byTaxonomy as $taxonomyHandle => $scopeTemplates) {
            if ($taxonomyHandle === '' || ! in_array($taxonomyHandle, $enabled, true)) {
                continue;
            }

            if (! TaxonomyFacade::find($taxonomyHandle)) {
                continue;
            }

            /** @var Collection<int, Entry> $scopeTemplates */
            $scopeKey = 'taxonomy:'.$taxonomyHandle;
            $stats->scope($scopeKey, 'taxonomy', (string) $taxonomyHandle);

            $terms = TermFacade::query()
                ->where('taxonomy', $taxonomyHandle)
                ->where('site', $site)
                ->get();

            foreach ($terms as $term) {
                if (! $term instanceof LocalizedTerm) {
                    continue;
                }

                if (! $term->published()) {
                    continue;
                }

                $this->evaluateContentItem(
                    $items,
                    $stats,
                    $term,
                    $scopeTemplates,
                    ReportItemType::Term,
                    'taxonomy',
                    (string) $taxonomyHandle,
                    $scopeKey,
                );
            }
        }
    }

    /**
     * @param  Collection<int, Entry>  $templates
     * @param  Collection<int, ReportItem>  $items
     */
    protected function scanRunway(Collection $templates, Collection $items, ReportScanStats $stats): void
    {
        if (! RunwaySupport::isInstalled()) {
            return;
        }

        $byResource = $templates
            ->filter(fn (Entry $template): bool => $this->stringField($template->blueprint_type) === 'runway')
            ->groupBy(fn (Entry $template): string => $this->scopeHandleFromField($template->use_for_runway));

        foreach ($byResource as $resourceHandle => $resourceTemplates) {
            if ($resourceHandle === '' || ! RunwaySupport::isHandleEnabled((string) $resourceHandle)) {
                continue;
            }

            $resource = RunwaySupport::findResource((string) $resourceHandle);

            if (! $resource instanceof Resource) {
                continue;
            }

            /** @var Collection<int, Entry> $resourceTemplates */
            $scopeKey = 'runway:'.$resourceHandle;
            $scope = $stats->scope($scopeKey, 'runway', (string) $resourceHandle);

            $models = $resource->model()->newQuery()->get();

            foreach ($models as $model) {
                $itemKey = $scopeKey.':'.$this->itemId($model);
                $stats->itemsScanned++;
                $scope->itemsScanned++;

                $hadIncomplete = false;

                foreach ($resourceTemplates as $template) {
                    $before = $items->count();
                    $this->evaluateCompleteness(
                        $items,
                        $model,
                        $template,
                        ReportItemType::Runway,
                        'runway',
                        (string) $resourceHandle,
                    );

                    if ($items->count() > $before) {
                        $hadIncomplete = true;
                    }
                }

                $stats->itemsWithTemplate++;
                $scope->itemsWithTemplate++;

                if (! $hadIncomplete) {
                    $stats->itemsComplete++;
                    $scope->itemsComplete++;
                } else {
                    $stats->itemsWithErrors[$itemKey] = true;
                    $scope->itemsWithErrors[$itemKey] = true;
                }
            }
        }
    }

    /**
     * @param  Collection<int, ReportItem>  $items
     * @param  Collection<int, Entry>  $scopeTemplates
     */
    protected function evaluateContentItem(
        Collection $items,
        ReportScanStats $stats,
        Entry|LocalizedTerm $item,
        Collection $scopeTemplates,
        ReportItemType $itemType,
        string $scopeType,
        string $scopeHandle,
        string $scopeKey,
    ): void {
        $itemKey = $scopeKey.':'.$this->itemId($item);
        $scope = $stats->scope($scopeKey, $scopeType, $scopeHandle);

        $stats->itemsScanned++;
        $scope->itemsScanned++;

        $assigned = $this->assignedTemplateIds($item);
        $assignedSet = array_fill_keys(array_filter($assigned), true);

        $automaticTemplates = $scopeTemplates
            ->filter(fn (Entry $template): bool => (bool) $template->apply_automatically)
            ->values();

        $hasMissingAutomatic = false;
        $checkedTemplateIds = [];
        $hadIncomplete = false;

        foreach ($automaticTemplates as $template) {
            $templateId = (string) $template->id();
            $stats->coverageExpected++;
            $scope->coverageExpected++;

            if (! isset($assignedSet[$templateId])) {
                $hasMissingAutomatic = true;
                $items->push($this->makeIssueItem(
                    ReportIssueType::MissingAutomaticTemplate,
                    $item,
                    $template,
                    $itemType,
                    $scopeType,
                    $scopeHandle,
                ));
                $stats->itemsWithErrors[$itemKey] = true;
                $scope->itemsWithErrors[$itemKey] = true;

                continue;
            }

            $stats->coveragePresent++;
            $scope->coveragePresent++;
            $checkedTemplateIds[$templateId] = true;

            $before = $items->count();
            $this->evaluateCompleteness($items, $item, $template, $itemType, $scopeType, $scopeHandle);
            if ($items->count() > $before) {
                $hadIncomplete = true;
                $stats->itemsWithErrors[$itemKey] = true;
                $scope->itemsWithErrors[$itemKey] = true;
            }
        }

        $templatesById = $scopeTemplates->keyBy(fn (Entry $template): string => (string) $template->id());

        foreach ($assigned as $assignedId) {
            if ($assignedId === '' || isset($checkedTemplateIds[$assignedId])) {
                continue;
            }

            $template = $templatesById->get($assignedId);
            if (! $template instanceof Entry) {
                continue;
            }

            $checkedTemplateIds[$assignedId] = true;
            $before = $items->count();
            $this->evaluateCompleteness($items, $item, $template, $itemType, $scopeType, $scopeHandle);
            if ($items->count() > $before) {
                $hadIncomplete = true;
                $stats->itemsWithErrors[$itemKey] = true;
                $scope->itemsWithErrors[$itemKey] = true;
            }
        }

        $hasAssigned = $assigned !== [] && array_filter($assigned) !== [];

        if ($hasAssigned) {
            $stats->itemsWithTemplate++;
            $scope->itemsWithTemplate++;

            if (! $hadIncomplete) {
                $stats->itemsComplete++;
                $scope->itemsComplete++;
            }
        } elseif ($scopeTemplates->isNotEmpty() && ! $hasMissingAutomatic) {
            $items->push($this->makeIssueItem(
                ReportIssueType::NoTemplateAssigned,
                $item,
                null,
                $itemType,
                $scopeType,
                $scopeHandle,
            ));
        }
    }

    /**
     * @param  Collection<int, ReportItem>  $items
     */
    protected function evaluateCompleteness(
        Collection $items,
        Entry|LocalizedTerm|Model $item,
        Entry $template,
        ReportItemType $itemType,
        string $scopeType,
        string $scopeHandle,
    ): void {
        /** @var array<int, array<string, mixed>>|null $schemas */
        $schemas = $template->schema_data;
        $schemas = $schemas ?? [];

        if ($schemas === []) {
            return;
        }

        $transformed = $this->structuredData->parseAndTransformSchemas($schemas, $item);
        $emptyFields = $this->completenessChecker->findEmptyFields($schemas, $transformed);

        foreach ($emptyFields as $empty) {
            $items->push($this->makeIssueItem(
                ReportIssueType::IncompleteField,
                $item,
                $template,
                $itemType,
                $scopeType,
                $scopeHandle,
                $empty['schema_type'],
                $empty['field_path'],
            ));
        }
    }

    protected function makeIssueItem(
        ReportIssueType $issueType,
        Entry|LocalizedTerm|Model $item,
        ?Entry $template,
        ReportItemType $itemType,
        string $scopeType,
        string $scopeHandle,
        ?string $schemaType = null,
        ?string $fieldPath = null,
    ): ReportItem {
        $templateTitle = $this->stringField($template?->title);

        return ReportItem::make([
            'id' => (string) Str::uuid(),
            'issue_type' => $issueType->value,
            'severity' => $issueType->severity()->value,
            'item_type' => $itemType->value,
            'item_id' => $this->itemId($item),
            'item_title' => $this->itemTitle($item),
            'item_edit_url' => $this->itemEditUrl($item, $itemType, $scopeHandle),
            'item_url' => $this->itemUrl($item),
            'template_id' => $template ? (string) $template->id() : null,
            'template_title' => $templateTitle !== '' ? $templateTitle : null,
            'schema_type' => $schemaType,
            'field_path' => $fieldPath,
            'scope_handle' => $scopeHandle,
            'scope_type' => $scopeType,
        ]);
    }

    /**
     * @param  Collection<int, ReportItem>  $items
     * @return array<string, mixed>
     */
    protected function summarize(Collection $items, ReportScanStats $stats): array
    {
        $missingAutomatic = $items->where('issue_type', ReportIssueType::MissingAutomaticTemplate->value)->count();
        $noTemplate = $items->where('issue_type', ReportIssueType::NoTemplateAssigned->value)->count();
        $incomplete = $items->where('issue_type', ReportIssueType::IncompleteField->value)->count();
        $errorCount = $items->filter(fn (ReportItem $item): bool => $item->severity()->value === 'error')->count();
        $warningCount = $items->filter(fn (ReportItem $item): bool => $item->severity()->value === 'warning')->count();

        $itemsScanned = $stats->itemsScanned;
        $itemsWithErrors = count($stats->itemsWithErrors);
        $cleanItems = max(0, $itemsScanned - $itemsWithErrors);

        $scopes = [];
        foreach ($stats->scopes as $scope) {
            $scopeScanned = $scope->itemsScanned;
            $scopeErrors = count($scope->itemsWithErrors);
            $scopeErrorItems = $items
                ->filter(fn (ReportItem $item): bool => $item->scope_type === $scope->scopeType
                    && $item->scope_handle === $scope->scopeHandle
                    && $item->severity()->value === 'error')
                ->count();
            $scopeWarningItems = $items
                ->filter(fn (ReportItem $item): bool => $item->scope_type === $scope->scopeType
                    && $item->scope_handle === $scope->scopeHandle
                    && $item->severity()->value === 'warning')
                ->count();

            $scopes[] = [
                'key' => $scope->key,
                'scope_type' => $scope->scopeType,
                'scope_handle' => $scope->scopeHandle,
                'items_scanned' => $scopeScanned,
                'error_count' => $scopeErrorItems,
                'warning_count' => $scopeWarningItems,
                'coverage_percent' => $this->percent($scope->coveragePresent, $scope->coverageExpected),
                'completeness_percent' => $this->percent($scope->itemsComplete, $scope->itemsWithTemplate),
                'clean_percent' => $this->percent(max(0, $scopeScanned - $scopeErrors), $scopeScanned),
            ];
        }

        return [
            'items_scanned' => $itemsScanned,
            'error_count' => $errorCount,
            'warning_count' => $warningCount,
            'missing_automatic_template_count' => $missingAutomatic,
            'no_template_assigned_count' => $noTemplate,
            'incomplete_field_count' => $incomplete,
            'coverage_expected' => $stats->coverageExpected,
            'coverage_present' => $stats->coveragePresent,
            'items_with_template' => $stats->itemsWithTemplate,
            'items_complete' => $stats->itemsComplete,
            'coverage_percent' => $this->percent($stats->coveragePresent, $stats->coverageExpected),
            'completeness_percent' => $this->percent($stats->itemsComplete, $stats->itemsWithTemplate),
            'clean_percent' => $this->percent($cleanItems, $itemsScanned),
            'scopes' => $scopes,
        ];
    }

    protected function percent(int $numerator, int $denominator): float
    {
        if ($denominator === 0) {
            return 100.0;
        }

        return round(($numerator / $denominator) * 100, 1);
    }

    /**
     * @return array<int, string>
     */
    protected function assignedTemplateIds(Entry|LocalizedTerm|Model $item): array
    {
        if ($item instanceof Model) {
            return [];
        }

        $templates = $item->structured_data_templates;

        if ($templates instanceof Collection) {
            $templates = $templates->all();
        }

        if (! is_array($templates)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (mixed $id): string {
                if ($id instanceof Entry || $id instanceof LocalizedTerm) {
                    $resolved = $id->id();

                    return is_string($resolved) || is_numeric($resolved) ? (string) $resolved : '';
                }

                return is_string($id) || is_numeric($id) ? (string) $id : '';
            },
            $templates,
        )));
    }

    protected function itemId(Entry|LocalizedTerm|Model $item): string
    {
        if ($item instanceof Entry) {
            $id = $item->id();

            return is_string($id) || is_numeric($id) ? (string) $id : '';
        }

        if ($item instanceof LocalizedTerm) {
            $id = $item->id();

            return is_string($id) || is_numeric($id) ? (string) $id : '';
        }

        $key = $item->getKey();

        return is_string($key) || is_numeric($key) ? (string) $key : '';
    }

    protected function itemTitle(Entry|LocalizedTerm|Model $item): ?string
    {
        if ($item instanceof Entry || $item instanceof LocalizedTerm) {
            $title = $this->stringField($item->title);

            return $title !== '' ? $title : ($item instanceof LocalizedTerm ? $item->slug() : null);
        }

        foreach (['title', 'name', 'label'] as $attribute) {
            $value = $item->getAttribute($attribute);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    protected function itemUrl(Entry|LocalizedTerm|Model $item): ?string
    {
        try {
            if ($item instanceof Entry || $item instanceof LocalizedTerm) {
                $url = $item->absoluteUrl();

                return is_string($url) ? $url : null;
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    protected function itemEditUrl(Entry|LocalizedTerm|Model $item, ReportItemType $itemType, string $scopeHandle): ?string
    {
        try {
            return match ($itemType) {
                ReportItemType::Entry => cp_route('collections.entries.edit', [
                    'collection' => $scopeHandle,
                    'entry' => $this->itemId($item),
                ]),
                ReportItemType::Term => cp_route('taxonomies.terms.edit', [
                    'taxonomy' => $scopeHandle,
                    'term' => $item instanceof LocalizedTerm ? $item->slug() : $this->itemId($item),
                ]),
                ReportItemType::Runway => RunwaySupport::isInstalled()
                    ? cp_route('runway.edit', [
                        'resource' => $scopeHandle,
                        'model' => $this->itemId($item),
                    ])
                    : null,
            };
        } catch (Throwable) {
            return null;
        }
    }

    protected function scopeHandleFromField(mixed $value): string
    {
        if ($value instanceof LabeledValue) {
            $value = $value->value();
        }

        if (is_string($value) || is_numeric($value)) {
            return (string) $value;
        }

        if ($value instanceof EntryCollection || $value instanceof Taxonomy) {
            return $value->handle();
        }

        if ($value instanceof Collection) {
            return $this->scopeHandleFromField($value->first());
        }

        if (is_array($value)) {
            return $this->scopeHandleFromField($value[0] ?? null);
        }

        return '';
    }

    protected function stringField(mixed $value): string
    {
        if ($value instanceof LabeledValue) {
            $value = $value->value();
        }

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    protected function prune(ReportRepository $repository): void
    {
        $days = config()->integer('justbetter.structured-data.reports.retention_days', 90);

        if ($days > 0) {
            $repository->pruneOlderThan($days);
        }
    }
}
