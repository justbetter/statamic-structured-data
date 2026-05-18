<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerFactory;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
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
    public function formatJsonLd(array $schema, bool $json = false, EntryContract|Page|LocalizedTerm|TermContract|null $item = null): string
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
     * @param  mixed  $schemas
     * @return array<int, array<string, mixed>>
     */
    public function parseAndTransformSchemas($schemas, EntryContract|Page|LocalizedTerm|TermContract|null $item = null): array
    {
        if (! $item instanceof EntryContract && ! $item instanceof TermContract) {
            return [];
        }

        $parsedData = $this->parser->parse($schemas, $item);
        $transformedData = [];

        if (is_array($parsedData)) {
            foreach ($parsedData as $schema) {
                if (is_array($schema)) {
                    $transformedData[] = $this->transformSchema($schema, $item);
                }
            }
        }

        return $transformedData;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    public function transformSchema(array $schema, EntryContract|Page|LocalizedTerm|TermContract|null $item = null): array
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
                $transformedValue = $this->transformField($field, $item, $result);

                if ($transformedValue === null) {
                    continue;
                }

                $result[$key] = $transformedValue;
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
     * @param  array<string, mixed>  $result
     */
    protected function transformField(array $field, EntryContract|Page|LocalizedTerm|TermContract|null $item = null, array &$result = []): mixed
    {
        $type = $this->normalizeFieldType($field['type'] ?? null);

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
        $transformer = $this->transformerFactory->getTransformer($type);
        $transformedValue = $transformer->transform($field, $item);

        /** @var array<string, mixed>|null $config */
        $config = $field['config'] ?? null;

        // Handle flat mode for replicator_object_array: keep flat object under the field key
        if ($type === 'replicator_object_array'
            && is_array($config)
            && ($config['flat'] ?? false) === true
            && is_array($transformedValue)
            && $this->isAssociativeArray($transformedValue)) {
            return $transformedValue;
        }

        return $transformedValue;
    }

    protected function normalizeFieldType(mixed $type): ?string
    {
        if (is_string($type)) {
            return $type;
        }

        if (is_array($type) && is_string($type['value'] ?? null)) {
            return $type['value'];
        }

        return null;
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
