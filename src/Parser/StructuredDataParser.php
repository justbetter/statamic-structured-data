<?php

namespace Justbetter\StatamicStructuredData\Parser;

use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Facades\Term;
use Statamic\Sites\Site;
use Statamic\View\Antlers\Antlers;

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
                return (string) (new Antlers)->parse($data, $this->getParseContext($item));
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
        $objectData['fields'] = array_merge([$objectTypeData], $objectData['fields'] ?? []);

        return $objectData;
    }
}
