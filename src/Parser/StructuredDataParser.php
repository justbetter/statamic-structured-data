<?php

namespace Justbetter\StatamicStructuredData\Parser;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Term;
use Statamic\Fields\Value;
use Statamic\Fields\Values;
use Statamic\Fieldtypes\Bard;
use Statamic\Fieldtypes\Bard\Augmentor as BardAugmentor;
use Statamic\Sites\Site;
use Statamic\View\Antlers\Antlers;
use Statamic\View\Antlers\AntlersString;

class StructuredDataParser
{
    protected StructuredDataService $structuredDataService;

    public function __construct()
    {
        $this->structuredDataService = new StructuredDataService($this);
    }

    /**
     * @param  mixed  $data
     */
    public function parse($data, EntryContract|TermContract $item): mixed
    {
        return $this->parseAntlersInData($data, $item);
    }

    /**
     * @param  mixed  $data
     */
    protected function parseAntlersInData($data, EntryContract|TermContract $item): mixed
    {
        if (is_string($data)) {
            if (str_contains($data, '{{')) {
                $parsed = (new Antlers)->parse($data, $this->getParseContext($item));

                return $this->normalizeParsedData($parsed, $item, $data);
            }

            if (str_contains($data, '@dataObject::')) {
                $objectSlug = explode('::', $data)[1];
                $objectData = $this->getObjectData($objectSlug);

                return $this->structuredDataService->transformSchema($objectData);
            }

            return $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->parseAntlersInData($value, $item);
            }
        }

        return $data;
    }

    protected function normalizeParsedData(mixed $parsed, EntryContract|TermContract $item, ?string $sourceTemplate = null): mixed
    {
        if ($parsed instanceof AntlersString) {
            $parsedString = (string) $parsed;
            if ($parsedString !== '') {
                return $parsedString;
            }

            return $this->resolveSourceTemplateValue($sourceTemplate, $item) ?? $parsedString;
        }

        return $this->normalizeResolvedValue($parsed, $item);
    }

    protected function resolveSourceTemplateValue(?string $sourceTemplate, EntryContract|TermContract $item): mixed
    {
        if (! is_string($sourceTemplate)) {
            return null;
        }

        // Only handle plain single-variable tags like {{ field }} or {{ parent.child }}.
        if (! preg_match('/^\s*\{\{\s*([a-zA-Z0-9_:\.-]+)\s*\}\}\s*$/', $sourceTemplate, $matches)) {
            return null;
        }

        $variablePath = str_replace(':', '.', $matches[1]);
        $value = data_get($this->getParseContext($item), $variablePath);

        return $this->normalizeResolvedValue($value, $item);
    }

    protected function normalizeResolvedValue(mixed $value, EntryContract|TermContract $item): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        if ($value instanceof Value) {
            if ($this->isBardValue($value)) {
                return $this->renderBardValueToHtml($value);
            }

            return $this->parseAntlersInData($value->value(), $item);
        }

        if ($value instanceof Values) {
            $value = $value->all();
        }

        if ($value instanceof Collection) {
            $value = $value->all();
        }

        if (is_array($value)) {
            $bardHtml = $this->renderHtmlFromBardSegments($value);

            return $bardHtml ?? $this->parseAntlersInData($value, $item);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }

    protected function isBardValue(Value $value): bool
    {
        try {
            return $value->fieldtype() instanceof Bard;
        } catch (\Throwable) {
            return false;
        }
    }

    protected function renderBardValueToHtml(Value $value): string
    {
        /** @var Bard $bardFieldtype */
        $bardFieldtype = $value->fieldtype();
        $rawValue = $value->raw();

        if (is_string($rawValue)) {
            return $rawValue;
        }

        if (! is_array($rawValue)) {
            return '';
        }

        $html = (new BardAugmentor($bardFieldtype))->convertToHtml($rawValue);

        return (string) preg_replace('/<set>.*?<\/set>/s', '', $html);
    }

    /**
     * @param  array<int|string, mixed>  $segments
     */
    protected function renderHtmlFromBardSegments(array $segments): ?string
    {
        if (! array_is_list($segments)) {
            return null;
        }

        $html = '';
        $hasBardSegments = false;

        foreach ($segments as $segment) {
            if ($segment instanceof Values) {
                $segment = $segment->all();
            }

            if ($segment instanceof Value) {
                $segment = $segment->value();
            }

            if ($segment instanceof Collection) {
                $segment = $segment->all();
            }

            if (! is_array($segment)) {
                return null;
            }

            $type = $segment['type'] ?? null;

            if (! is_string($type) || $type === '') {
                return null;
            }

            $hasBardSegments = true;

            if ($type !== 'text') {
                continue;
            }

            $text = $segment['text'] ?? '';

            if ($text instanceof Value) {
                $text = $text->value();
            }

            if ($text instanceof Collection) {
                $text = $text->implode('');
            }

            if (! is_string($text)) {
                continue;
            }

            $html .= $text;
        }

        if (! $hasBardSegments || $html === '') {
            return null;
        }

        return $html;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getParseContext(EntryContract|TermContract $item): array
    {
        /** @var Site $site */
        $site = SiteFacade::current();
        $siteAugmented = $site->toAugmentedArray();
        $itemAugmented = ($item instanceof Augmentable) ? $item->toAugmentedArray() : [];

        return array_merge(
            ['config' => config()->all()],
            ['site' => $siteAugmented],
            ['absolute_url' => method_exists($item, 'absoluteUrl') ? $item->absoluteUrl() : ''],
            $itemAugmented
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getObjectData(string $objectSlug): array
    {
        /** @var Site $site */
        $site = SiteFacade::current();

        $dataObject = Term::query()
            ->where('taxonomy', 'structured_data_objects')
            ->where('site', $site->handle())
            ->where('slug', $objectSlug)
            ->first();

        if (! $dataObject || ! isset($dataObject->object_data) || ! is_array($dataObject->object_data)) {
            return [];
        }

        $objectType = $dataObject->object_type ?? '';
        $objectTypeData = [
            'key' => '@type',
            'type' => 'string',
            'value' => $objectType,
            'fields' => [],
            'values' => [],
        ];

        $objectData = $dataObject->object_data;
        $existingFields = is_array($objectData['fields'] ?? null) ? $objectData['fields'] : [];
        $objectData['fields'] = array_merge([$objectTypeData], $existingFields);

        return $objectData;
    }
}
