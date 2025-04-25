<?php

namespace Justbetter\StatamicStructuredData\Parser;

use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\View\Antlers\Antlers;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;

class StructuredDataParser
{
    protected $structuredDataService;

    public function __construct()
    {
        $this->structuredDataService = new StructuredDataService($this);
    }

    public function parse($data, $entry)
    {
        return $this->parseAntlersInData($data, $entry);
    }

    protected function parseAntlersInData($data, $entry)
    {
        if (is_string($data)) {
            if (str_contains($data, '{{')) {
                return (string) (new Antlers)->parse($data, $this->getParseContext($entry));
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
                $data[$key] = $this->parseAntlersInData($value, $entry);
            }
        }

        return $data;
    }

    protected function getParseContext($entry): array
    {
        return array_merge(
            ['config' => config()->all()],
            ['site' => Site::current()->toAugmentedArray()],
            ['absolute_url' => $entry->absoluteUrl()],
            $entry->toAugmentedArray()
        );
    }

    protected function getObjectData($objectSlug): array
    {
        $dataObject = Term::query()
            ->where('taxonomy', 'structured_data_objects')
            ->where('site', Site::current()->handle())
            ->where('slug', $objectSlug)
            ->first();

        if (! $dataObject || !$dataObject->object_data) {
            return [];
        }

        $objectType = $dataObject->object_type;
        $objectTypeData = [
            'key' => '@type',
            'type' => 'string',
            'value' => $objectType ?? '',
            'fields' => [],
            'values' => [],
        ];

        $objectData = $dataObject->object_data;
        $objectData['fields'] = array_merge([$objectTypeData], $objectData['fields']);

        return $objectData;
    }
}
