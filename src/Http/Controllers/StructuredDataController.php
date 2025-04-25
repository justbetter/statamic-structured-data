<?php

namespace Justbetter\StatamicStructuredData\Http\Controllers;

use Illuminate\Http\Request;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Http\Controllers\CP\CpController;

class StructuredDataController extends CpController
{
    protected $parser;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
    }

    public function parseAntlersInData($data, $entry)
    {
        return $this->parser->parse($data, $entry);
    }

    protected function getParseContext($entry)
    {
        return array_merge(
            ['config' => config()->all()],
            ['site' => Site::current()->toAugmentedArray()],
            $entry->toAugmentedArray()
        );
    }

    public function getTemplates(Request $request)
    {
        $templateIds = $request->input('ids', []);
        $contentEntry = Entry::find($request->input('entry_id'));

        if (! $contentEntry) {
            return response()->json(['error' => 'Content entry not found'], 404);
        }

        $templates = collect($templateIds)
            ->map(function ($id) use ($contentEntry) {
                $entry = Entry::find($id);

                if (! $entry) {
                    return null;
                }

                $structuredData = $entry->schema_data;

                if (!$structuredData) {
                    return null;
                }

                $parsedData = $this->parseAntlersInData($structuredData, $contentEntry);

                return [
                    'id' => $entry->id(),
                    'title' => $entry->title,
                    'structuredData' => $parsedData,
                ];
            })
            ->filter()
            ->values();

        return response()->json($templates);
    }

    public function getAvailableVariables(Request $request)
    {
        $entry = Entry::find($request->input('entry_id'));

        $variables = [
            'config' => [
                'app' => [
                    ['name' => 'config:app:url', 'description' => 'Application URL'],
                    ['name' => 'config:app:name', 'description' => 'Application Name'],
                ],
                'site' => [
                    ['name' => 'site:name', 'description' => 'Site Name'],
                    ['name' => 'site:url', 'description' => 'Site URL'],
                ],
            ],
            'entry' => [],
        ];

        if (!$entry) {
            return response()->json($variables);
        }

        $blueprint = $entry->blueprint();
        $fields = $blueprint->fields()->all();

        $variables['entry'] = collect($fields)
            ->map(function ($field) {
                return [
                    'name' => $field->handle(),
                    'description' => $field->display(),
                ];
            })
            ->values()
            ->all();

        return response()->json($variables);
    }
}
