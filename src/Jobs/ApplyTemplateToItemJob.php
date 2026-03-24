<?php

namespace Justbetter\StatamicStructuredData\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Term as TermFacade;
use Statamic\Taxonomies\LocalizedTerm;

class ApplyTemplateToItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $itemId,
        public string $itemType,
        public string $templateId
    ) {}

    public function handle(): void
    {
        if ($this->itemType === 'entry') {
            $entry = EntryFacade::find($this->itemId);

            if (! $entry instanceof EntryModel) {
                return;
            }

            $this->applyTemplateToItem($entry);
        } elseif ($this->itemType === 'term') {
            $term = TermFacade::find($this->itemId);

            if (! $term instanceof LocalizedTerm) {
                return;
            }

            $this->applyTemplateToItem($term);
        }
    }

    protected function applyTemplateToItem(EntryModel|LocalizedTerm $item): void
    {
        $structuredDataTemplates = $item->get('structured_data_templates') ?? [];

        $templateIds = [];
        foreach ($structuredDataTemplates as $existingTemplate) {
            if (is_string($existingTemplate) || is_int($existingTemplate)) {
                $templateIds[] = (string) $existingTemplate;
            } elseif (is_object($existingTemplate) && method_exists($existingTemplate, 'id')) {
                $templateIds[] = (string) $existingTemplate->id();
            }
        }

        if (! in_array($this->templateId, $templateIds, true)) {
            $templateIds[] = $this->templateId;
            /** @var non-empty-list<string> $templateIds */
            $item->set('structured_data_templates', $templateIds);
            $item->saveQuietly();
        }
    }
}
