<?php

namespace Justbetter\StatamicStructuredData\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Http\Controllers\CP\CpController;

class StructuredDataController extends CpController
{
    protected StructuredDataParser $parser;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param  mixed  $data
     */
    public function parseAntlersInData($data, EntryContract|TermContract $entry): mixed
    {
        return $this->parser->parse($data, $entry);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getParseContext(EntryContract|TermContract $entry): array
    {
        $site = Site::current();
        $siteAugmented = ($site && $site instanceof Augmentable) ? $site->toAugmentedArray() : [];

        $entryAugmented = $entry instanceof Augmentable ? $entry->toAugmentedArray() : [];

        return array_merge(
            ['config' => config()->all()],
            ['site' => $siteAugmented],
            $entryAugmented
        );
    }

    public function getTemplates(Request $request): JsonResponse
    {
        /** @var array<int|string>|null $templateIds */
        $templateIds = $request->input('ids', []);
        $contentEntry = Entry::find($request->input('entry_id'));

        if (! $contentEntry) {
            $contentEntry = Term::find($request->input('entry_id'));
        }

        if (! $contentEntry instanceof EntryContract && ! $contentEntry instanceof TermContract) {
            return response()->json(['error' => 'Content entry not found'], 404);
        }

        /** @var array<int, array<string, mixed>> $templates */
        $templates = collect($templateIds ?? [])
            ->map(function ($id) use ($contentEntry) {
                $entry = Entry::find($id);

                if (! $entry instanceof EntryModel) {
                    return null;
                }

                $structuredData = $entry->get('schema_data');

                if ($structuredData === null) {
                    return null;
                }

                $structuredDataService = app(StructuredDataService::class);
                $transformedData = $structuredDataService->parseAndTransformSchemas($structuredData, $contentEntry);

                return [
                    'id' => $entry->id(),
                    'title' => $entry->get('title'),
                    'structuredData' => $transformedData,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return response()->json($templates);
    }

    public function getAvailableVariables(Request $request): JsonResponse
    {
        /** @var EntryContract|null $entry */
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

        if (! $entry instanceof EntryModel) {
            return response()->json($variables);
        }

        $blueprint = $entry->blueprint();
        $fieldsCollection = $blueprint->fields()->all();
        /** @var array<int, \Statamic\Fields\Field> $fields */
        $fields = array_values(is_array($fieldsCollection) ? $fieldsCollection : $fieldsCollection->all());

        $variables['entry'] = array_values(array_map(function ($field): array {
            return [
                'name' => $field->handle(),
                'description' => $field->display(),
            ];
        }, $fields));

        return response()->json($variables);
    }
}
