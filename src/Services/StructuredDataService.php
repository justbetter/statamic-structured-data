<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerFactory;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Structures\Page;
use Statamic\Taxonomies\LocalizedTerm;

class StructuredDataService
{
    protected StructuredDataParser $parser;

    protected FieldTransformerFactory $transformerFactory;

    public function __construct(StructuredDataParser $parser)
    {
        $this->parser = $parser;
        $this->transformerFactory = new FieldTransformerFactory;
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

                    $scripts[] = $this->formatJsonLd($parsedSchema, $json, $item);
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
    public function formatJsonLd(array $schema, bool $json = false, EntryContract|Page|LocalizedTerm|null $item = null): string
    {
        $transformedSchema = $this->transformSchema($schema, $item);
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
    public function transformSchema(array $schema, EntryContract|Page|LocalizedTerm|null $item = null): array
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
                $transformedValue = $this->transformField($field, $item);

                if (($field['type'] ?? null) === 'replicator_object_array'
                    && isset($field['config']['flat'])
                    && $field['config']['flat'] === true
                    && is_array($transformedValue)
                    && $this->isAssociativeArray($transformedValue)) {
                    $result = array_merge($result, $transformedValue);
                } else {
                    $result[$key] = $transformedValue;
                }
            }
        }

        return $result;
    }

    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function transformField(array $field, EntryContract|Page|LocalizedTerm|null $item = null): mixed
    {
        $type = $field['type'] ?? null;

        // Handle object and object_array types that need recursive schema transformation
        if ($type === 'object' && isset($field['value']) && is_array($field['value'])) {
            return $this->transformSchema($field['value'], $item);
        }

        if ($type === 'object_array' && isset($field['values']) && is_array($field['values'])) {
            $output = [];
            foreach ($field['values'] as $value) {
                if (is_array($value)) {
                    $output[] = $this->transformSchema($value, $item);
                }
            }

            return $output;
        }

        // Use transformer factory for all other field types
        $transformer = $this->transformerFactory->getTransformer(is_string($type) ? $type : null);

        return $transformer->transform($field, $item);
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
