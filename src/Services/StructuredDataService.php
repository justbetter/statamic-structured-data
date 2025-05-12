<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Structures\Page;

class StructuredDataService
{
    protected $parser;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
    }

    public function getJsonLdScripts($item): array
    {
        $templates = $this->getTemplates($item);

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
                $parsedSchemas = $this->parser->parse($schemas, $item);
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

    protected function getTemplates($item): array
    {
        if (! $item instanceof EntryContract && ! $item instanceof Page && ! $item instanceof LocalizedTerm) {
            return [];
        }

        if ($item instanceof Page) {
            $item = $item->entry();
        }

        $templates = $item->get('structured_data_templates');

        return $templates ?? [];
    }
}
