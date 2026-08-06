<?php

namespace Justbetter\StatamicStructuredData\Http\Controllers\CP;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;
use Justbetter\StatamicStructuredData\Contracts\ResolvesReportRepository;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Jobs\GenerateStructuredDataReportJob;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Auth\User as StatamicUser;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Sites\Site;
use Throwable;

class ReportController extends Controller
{
    /**
     * @codeCoverageIgnore
     */
    public function index(ResolvesReportRepository $repositories): Response
    {
        /** @var Site $selectedSite */
        $selectedSite = SiteFacade::selected();
        $site = $selectedSite->handle();

        $reports = $repositories->resolve()
            ->allForSite($site)
            ->map(fn (Report $report): array => $report->toSummaryArray())
            ->values()
            ->all();

        return Inertia::render('statamic-structured-data::Reports/Index', [
            'site' => $site,
            'reports' => $reports,
            'generateUrl' => cp_route('justbetter.structured-data.reports.generate'),
            'showUrlTemplate' => cp_route('justbetter.structured-data.reports.show', ['report' => '__REPORT_ID__']),
        ]);
    }

    /**
     * @codeCoverageIgnore
     */
    public function show(string $report, ResolvesReportRepository $repositories): Response
    {
        $stored = $repositories->resolve()->find($report);

        abort_unless($stored !== null, 404);

        /** @var Site $selectedSite */
        $selectedSite = SiteFacade::selected();
        abort_unless($stored->site === $selectedSite->handle(), 404);

        $issueType = request()->string('issue_type')->toString();
        $severityParam = request()->string('severity')->toString();
        $severity = $severityParam !== '' ? $severityParam : 'error';
        $scope = request()->string('scope')->toString();

        $items = $stored->items()
            ->when($issueType !== '', fn ($collection) => $collection->where('issue_type', $issueType))
            ->filter(fn (ReportItem $item): bool => $item->severity()->value === $severity)
            ->when($scope !== '', fn ($collection) => $collection->filter(
                fn (ReportItem $item): bool => $this->scopeKey($item) === $scope
            ))
            ->values()
            ->map(fn (ReportItem $item): array => $item->toArray())
            ->all();

        return Inertia::render('statamic-structured-data::Reports/Show', [
            'report' => $stored->toSummaryArray(),
            'items' => $items,
            'filters' => [
                'issue_type' => $issueType,
                'severity' => $severity,
                'scope' => $scope,
            ],
            'schemaValidatorUrl' => 'https://validator.schema.org/',
            'indexUrl' => cp_route('justbetter.structured-data.reports.index'),
            'jsonLdUrlTemplate' => cp_route('justbetter.structured-data.reports.json-ld', [
                'report' => $stored->id,
                'item' => '__ITEM_ID__',
            ]),
        ]);
    }

    /**
     * @codeCoverageIgnore
     */
    public function generate(Request $request, GeneratesStructuredDataReport $generator): JsonResponse
    {
        $validated = $request->validate([
            'template_id' => ['nullable', 'string'],
            'queue' => ['sometimes', 'boolean'],
        ]);

        /** @var Site $selectedSite */
        $selectedSite = SiteFacade::selected();

        $user = $request->user();
        $actor = $user instanceof StatamicUser ? $user->email() : null;

        /** @var array{site: string, template_id?: string|null, triggered_by?: string|null, actor?: string|null} $options */
        $options = [
            'site' => $selectedSite->handle(),
            'template_id' => isset($validated['template_id']) && is_string($validated['template_id'])
                ? $validated['template_id']
                : null,
            'triggered_by' => 'cp',
            'actor' => $actor,
        ];

        $useQueue = ($validated['queue'] ?? false) === true
            || config('queue.default') !== 'sync';

        if ($useQueue) {
            GenerateStructuredDataReportJob::dispatch($options);

            return response()->json([
                'queued' => true,
                'message' => __('Report generation has been queued.'),
            ]);
        }

        try {
            $report = $generator->generate($options);
        } catch (Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'queued' => false,
            'report' => $report->toSummaryArray(),
            'showUrl' => cp_route('justbetter.structured-data.reports.show', ['report' => $report->id]),
        ]);
    }

    /**
     * @codeCoverageIgnore
     */
    public function jsonLd(
        string $report,
        string $item,
        ResolvesReportRepository $repositories,
        StructuredDataService $structuredData,
    ): JsonResponse {
        $stored = $repositories->resolve()->find($report);
        abort_unless($stored !== null, 404);

        /** @var ReportItem|null $reportItem */
        $reportItem = $stored->items()->firstWhere('id', $item);
        abort_unless($reportItem instanceof ReportItem, 404);

        $content = $this->resolveContent($reportItem);

        if ($content === null) {
            return response()->json(['scripts' => []], 404);
        }

        $resourceHandle = $reportItem->scope_type === ReportItemType::Runway->value
            ? $reportItem->scope_handle
            : null;

        return response()->json([
            'scripts' => $structuredData->getJsonLdScripts($content, true, $resourceHandle),
            'url' => $reportItem->item_url,
        ]);
    }

    /**
     * @codeCoverageIgnore
     */
    protected function resolveContent(ReportItem $reportItem): EntryContract|TermContract|Model|null
    {
        $itemId = $reportItem->item_id;

        return match ($reportItem->itemType()) {
            ReportItemType::Entry => EntryFacade::find($itemId),
            ReportItemType::Term => TermFacade::find($itemId),
            ReportItemType::Runway => null,
        };
    }

    /**
     * @codeCoverageIgnore
     */
    protected function scopeKey(ReportItem $item): string
    {
        return $item->scope_type.':'.$item->scope_handle;
    }
}
