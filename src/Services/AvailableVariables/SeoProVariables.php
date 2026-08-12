<?php

namespace Justbetter\StatamicStructuredData\Services\AvailableVariables;

use Statamic\Entries\Collection;
use Statamic\SeoPro\Cascade;
use Statamic\Taxonomies\Taxonomy;

class SeoProVariables
{
    public function isInstalled(): bool
    {
        return class_exists(Cascade::class);
    }

    public function isEnabledForSection(Collection|Taxonomy $section): bool
    {
        if (! $this->isInstalled()) {
            return false;
        }

        return $section->cascade('seo') !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function forSection(Collection|Taxonomy $section): array
    {
        if (! $this->isEnabledForSection($section)) {
            return [];
        }

        return $this->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        $variables = [
            'title' => 'Meta Title',
            'compiled_title' => 'Compiled Title',
            'description' => 'Meta Description',
            'canonical_url' => 'Canonical URL',
            'og_title' => 'Open Graph Title',
            'og_type' => 'Open Graph Type',
            'image' => 'Open Graph Image',
        ];

        return collect($variables)
            ->map(fn (string $description, string $handle): array => [
                'name' => 'seo:'.$handle,
                'description' => $description,
                'children' => [],
            ])
            ->values()
            ->all();
    }
}
