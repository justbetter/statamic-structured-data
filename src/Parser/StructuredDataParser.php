<?php

namespace Justbetter\StatamicStructuredData\Parser;

use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\View\Antlers\Antlers;

class StructuredDataParser
{
    protected $structuredDataService;

    public function __construct()
    {
        $this->structuredDataService = new StructuredDataService($this);
    }

    public function parse($data, $item)
    {
        return $this->parseAntlersInData($data, $item);
    }

    protected function parseAntlersInData($data, $item)
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

    protected function getParseContext($item): array
    {
        return array_merge(
            ['config' => config()->all()],
            ['site' => Site::current()->toAugmentedArray()],
            ['absolute_url' => $item->absoluteUrl()],
            $item->toAugmentedArray()
        );
    }

    protected function getObjectData($objectSlug): array
    {
        $dataObject = Term::query()
            ->where('taxonomy', 'structured_data_objects')
            ->where('site', Site::current()->handle())
            ->where('slug', $objectSlug)
            ->first();

        if (! $dataObject || ! $dataObject->object_data) {
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
