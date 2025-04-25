<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;

class StructuredDataService
{
    protected $parser;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
    }

    public function getJsonLdScripts($entry): array
    {
        if (! $entry instanceof EntryContract && ! $entry instanceof Page) {
            return [];
        }

        if ($entry instanceof Page) {
            $entry = $entry->entry();
        }

        $templates = $entry->get('structured_data_templates');

        if (!($templates ?? false)) {
            return [];
        }

        $scripts = [];

        foreach ($templates as $templateId) {
            $template = EntryFacade::find($templateId);

            if (! $template) {
                continue;
            }

            $schemas = $template->get('schema_data') ?? [];

            if (empty($schemas)) {
                continue;
            }

            try {
                $parsedSchemas = $this->parser->parse($schemas, $entry);
                foreach ($parsedSchemas as $parsedSchema) {
                    $scripts[] = $this->formatJsonLd($parsedSchema);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $scripts;
    }

    public function formatJsonLd(array $schema): string
    {
        $transformedSchema = $this->transformSchema($schema);

        return sprintf(
            '<script type="application/ld+json">%s</script>',
            json_encode($transformedSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    public function transformSchema(array $schema): array
    {
        $result = [];

        if (isset($schema['specialProps'])) {
            if (isset($schema['specialProps']['context'])) {
                $result['@context'] = $schema['specialProps']['context'];
            }
            if (isset($schema['specialProps']['type'])) {
                $result['@type'] = $schema['specialProps']['type'];
            }
            if (isset($schema['specialProps']['id'])) {
                $result['@id'] = $schema['specialProps']['id'];
            }
        }

        if (isset($schema['fields']) && is_array($schema['fields'])) {
            foreach ($schema['fields'] as $field) {
                if (! isset($field['key'])) {
                    continue;
                }

                $key = $field['key'];

                if ($field['type'] === 'array' && isset($field['values'])) {
                    $result[$key] = $field['values'];
                } elseif ($field['type'] === 'object' && isset($field['value'])) {
                    $result[$key] = $this->transformSchema($field['value']);
                } else {
                    $result[$key] = $field['value'] ?? null;
                }
            }
        }

        return $result;
    }
}
