<?php

namespace Justbetter\StatamicStructuredData\Fieldtypes;

use Statamic\Entries\Entry;
use Statamic\Fields\Fieldtype;
use Statamic\Taxonomies\LocalizedTerm;

class StructuredDataPreview extends Fieldtype
{
    /** @var array<string> */
    protected $categories = ['structured_data'];

    /** @var string */
    protected static $handle = 'structured_data_preview';

    /** @return null */
    public function defaultValue()
    {
        return null;
    }

    /** @return array<string, mixed> */
    public function preload(): array
    {
        return [
            'item_url' => $this->resolveItemUrl(),
            'schema_validator_url' => 'https://validator.schema.org/',
        ];
    }

    protected function resolveItemUrl(): ?string
    {
        $parent = $this->field?->parent();

        if (! $parent instanceof Entry && ! $parent instanceof LocalizedTerm) {
            return null;
        }

        try {
            $url = $parent->absoluteUrl();
        } catch (\Throwable) {
            return null;
        }

        return is_string($url) && $url !== '' ? $url : null;
    }
}
