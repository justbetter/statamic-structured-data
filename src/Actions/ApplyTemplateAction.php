<?php

namespace Justbetter\StatamicStructuredData\Actions;

use Illuminate\Support\Collection as SupportCollection;
use Justbetter\StatamicStructuredData\Jobs\ApplyTemplateToItemJob;
use Statamic\Actions\Action;
use Statamic\Entries\Collection;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Taxonomy as TaxonomyFacade;
use Statamic\Fields\LabeledValue;
use Statamic\Sites\Site;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class ApplyTemplateAction extends Action
{
    public static function title(): string
    {
        return __('Apply Template');
    }

    public function confirmationText(): string
    {
        return __('Are you sure you want to apply this template to all entries/terms in the selected collection/taxonomy?|Are you sure you want to apply this template to :count entries/terms in the selected collection/taxonomy?');
    }

    public function visibleTo(mixed $item): bool
    {
        if (! $item instanceof Entry || $item->collection()->handle() !== 'structured_data_templates') {
            return false;
        }

        return true;
    }

    /**
     * @param  SupportCollection<int, Entry>  $items
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function run($items, $values): array
    {
        $totalJobsDispatched = 0;

        $items->each(function (Entry $template) use (&$totalJobsDispatched) {
            $totalJobsDispatched += $this->applyTemplates($template);
        });

        return [
            'message' => __('Applied template to :count items.', ['count' => $totalJobsDispatched]),
        ];
    }

    public function applyTemplates(Entry $template): int
    {
        $blueprintType = $template->blueprint_type;
        if ($blueprintType instanceof LabeledValue) {
            $blueprintType = $blueprintType->value();
        }
        $blueprintType = is_string($blueprintType) ? $blueprintType : '';
        $templateId = (string) $template->id();

        if ($blueprintType === 'collection') {
            return $this->applyTemplateToCollection($template, $templateId);
        }

        if ($blueprintType === 'taxonomy') {
            return $this->applyTemplateToTaxonomy($template, $templateId);
        }

        return 0;
    }

    public function applyTemplateToCollection(Entry $template, string $templateId): int
    {
        $collectionValue = $template->use_for_collection;
        if (! $collectionValue) {
            return 0;
        }

        // @codeCoverageIgnoreStart
        $collection = $collectionValue instanceof Collection
            ? $collectionValue
            : CollectionFacade::find($collectionValue);

        if (! $collection instanceof Collection) {
            return 0;
        }
        // @codeCoverageIgnoreEnd

        /** @var Site $site */
        $site = $template->site();

        $entries = $collection
            ->queryEntries()
            ->where('site', $site->handle())
            ->get();

        $count = 0;

        $entries->each(function (Entry $entry) use ($templateId, &$count) {
            ApplyTemplateToItemJob::dispatch($entry->id(), 'entry', $templateId);
            $count++;
        });

        return $count;
    }

    public function applyTemplateToTaxonomy(Entry $template, string $templateId): int
    {
        $taxonomyValue = $template->use_for_taxonomy;

        if (! $taxonomyValue) {
            return 0;
        }

        $taxonomy = $taxonomyValue instanceof Taxonomy
            ? $taxonomyValue
            : TaxonomyFacade::find((string) $taxonomyValue);

        if (! $taxonomy instanceof Taxonomy) {
            return 0;
        }

        /** @var Site $site */
        $site = $template->site();

        $terms = $taxonomy
            ->queryTerms()
            ->where('site', $site->handle())
            ->get();

        $count = 0;

        $terms->each(function (LocalizedTerm $term) use ($templateId, &$count): void {
            ApplyTemplateToItemJob::dispatch($term->id(), 'term', $templateId);
            $count++;
        });

        return $count;
    }
}
