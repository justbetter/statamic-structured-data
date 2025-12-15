<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

class StructuredDataService
{
    protected StructuredDataParser $parser;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * @param  EntryContract|Page|LocalizedTerm  $item
     * @return array<int, string>
     */
    public function getJsonLdScripts($item, bool $json = false): array
    {
        $templates = $this->getTemplates($item);

        if (! $templates) {
            return [];
        }

        $scripts = [];

        foreach ($templates as $templateId) {
            $template = EntryFacade::find($templateId);

            if (! $template instanceof EntryModel) {
                continue;
            }

            /** @var array<int, array<string, mixed>>|null $schemas */
            $schemas = $template->get('schema_data');
            $schemas = $schemas ?? [];

            if (empty($schemas) || ! is_array($schemas)) {
                continue;
            }

            try {
                $parsedSchemas = $this->parser->parse($schemas, $item);
                if (! is_array($parsedSchemas)) {
                    continue;
                }

                foreach ($parsedSchemas as $parsedSchema) {
                    if (! is_array($parsedSchema)) {
                        continue;
                    }

                    $scripts[] = $this->formatJsonLd($parsedSchema, $json);
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $scripts;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function formatJsonLd(array $schema, bool $json = false): string
    {
        $transformedSchema = $this->transformSchema($schema);
        $encodedSchema = json_encode($transformedSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($json) {
            return $encodedSchema ?: '';
        }

        return sprintf(
            '<script type="application/ld+json">%s</script>',
            $encodedSchema ?: ''
        );
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function transformSchema(array $schema): array
    {
        $result = [];

        if (isset($schema['specialProps']) && is_array($schema['specialProps'])) {
            $specialProps = $schema['specialProps'];
            if (isset($specialProps['context'])) {
                $result['@context'] = $specialProps['context'];
            }
            if (isset($specialProps['type'])) {
                $result['@type'] = $specialProps['type'];
            }
            if (isset($specialProps['id'])) {
                $result['@id'] = $specialProps['id'];
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
                } elseif ($field['type'] === 'object_array' && isset($field['values'])) {
                    foreach ($field['values'] as $value) {
                        $result[$key][] = $this->transformSchema($value);
                    }
                } elseif ($field['type'] === 'numeric' && isset($field['value'])) {
                    $result[$key] = (float) $field['value'];
                } else {
                    $result[$key] = $field['value'] ?? null;
                }
            }
        }

        return $result;
    }

    /**
     * @param  EntryContract|Page|LocalizedTerm|mixed  $item
     * @return array<int|string, mixed>
     */
    protected function getTemplates($item): array
    {
        if ($item instanceof Page) {
            /** @var EntryContract $item */
            $item = $item->entry();
        }

        if ($item instanceof EntryModel) {
            /** @var array<int|string, mixed>|null $templates */
            $templates = $item->get('structured_data_templates');

            return is_array($templates) ? $templates : [];
        }

        if ($item instanceof LocalizedTerm) {
            /** @var array<int|string, mixed>|null $templates */
            $templates = $item->get('structured_data_templates');

            return is_array($templates) ? $templates : [];
        }

        return [];
    }
}
