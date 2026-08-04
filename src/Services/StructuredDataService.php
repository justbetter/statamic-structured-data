<?php

namespace Justbetter\StatamicStructuredData\Services;

use Illuminate\Database\Eloquent\Model;
use Justbetter\StatamicStructuredData\Parser\StructuredDataParser;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerFactory;
use Justbetter\StatamicStructuredData\Support\RunwaySupport;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;
use Statamic\Entries\Entry as EntryModel;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Query\EloquentQueryBuilder;
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
     * @param  EntryContract|Page|LocalizedTerm|TermContract|Model  $item
     * @return array<int, string>
     */
    public function getJsonLdScripts($item, bool $json = false, ?string $resourceHandle = null): array
    {
        $templates = $this->getTemplates($item, $resourceHandle);

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

            if (empty($schemas)) {
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

                    /** @var array<string, mixed> $parsedSchema */
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
     * @param  EntryContract|Page|LocalizedTerm|TermContract|Model|null  $item
     */
    public function formatJsonLd(array $schema, bool $json = false, $item = null): string
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
     * @param  EntryContract|Page|LocalizedTerm|TermContract|Model|null  $item
     * @return array<int, array<string, mixed>>
     */
    public function parseAndTransformSchemas($schemas, $item = null): array
    {
        if (
            ! $item instanceof EntryContract
            && ! $item instanceof TermContract
            && ! $item instanceof Model
        ) {
            return [];
        }

        $parsedData = $this->parser->parse($schemas, $item);
        $transformedData = [];

        if (is_array($parsedData)) {
            foreach ($parsedData as $schema) {
                if (is_array($schema)) {
                    /** @var array<string, mixed> $schema */
                    $transformedData[] = $this->transformSchema($schema, $item);
                }
            }
        }

        return $transformedData;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  EntryContract|Page|LocalizedTerm|TermContract|Model|null  $item
     * @return array<string, mixed>
     */
    public function transformSchema(array $schema, $item = null): array
    {
        /** @var array<string, mixed> $result */
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
                if (! is_array($field) || ! isset($field['key']) || ! is_string($field['key']) || $field['key'] === '') {
                    continue;
                }

                /** @var array<string, mixed> $field */
                /** @var string $key */
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

    /**
     * @param  array<mixed, mixed>  $array
     */
    protected function isAssociativeArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_keys($array) !== range(0, count($array) - 1);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  EntryContract|Page|LocalizedTerm|TermContract|Model|null  $item
     * @param  array<string, mixed>  $result
     */
    protected function transformField(array $field, $item = null, array &$result = []): mixed
    {
        $type = $field['type'] ?? null;

        // Handle object and object_array types that need recursive schema transformation
        if ($type === 'object' && isset($field['value']) && is_array($field['value'])) {
            /** @var array<string, mixed> $fieldValue */
            $fieldValue = $field['value'];

            return $this->transformSchema($fieldValue, $item);
        }

        if ($type === 'object_array' && isset($field['values']) && is_array($field['values'])) {
            $output = [];
            foreach ($field['values'] as $value) {
                if (is_array($value)) {
                    /** @var array<string, mixed> $value */
                    $output[] = $this->transformSchema($value, $item);
                }
            }

            return $output;
        }

        // Use transformer factory for all other field types
        $transformer = $this->transformerFactory->getTransformer(is_string($type) ? $type : null);
        $transformedValue = $transformer->transform($field, $item);

        // Handle flat mode for replicator_object_array: keep flat object under the field key
        /** @var array<string, mixed>|null $config */
        $config = $field['config'] ?? null;

        if ($type === 'replicator_object_array'
            && is_array($config)
            && ($config['flat'] ?? false) === true
            && is_array($transformedValue)
            && $this->isAssociativeArray($transformedValue)) {
            return $transformedValue;
        }

        return $transformedValue;
    }

    /**
     * @param  EntryContract|Page|LocalizedTerm|Model|mixed  $item
     * @return array<int|string, mixed>
     */
    public function getTemplates($item, ?string $resourceHandle = null): array
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

        if ($item instanceof Model) {
            $handle = RunwaySupport::resolveResourceHandle($item, $resourceHandle);

            if (! $handle) {
                return [];
            }

            return $this->getRunwayTemplateIds($handle);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getRunwayTemplateIds(string $resourceHandle): array
    {
        if (! RunwaySupport::isHandleEnabled($resourceHandle)) {
            return [];
        }

        /** @var EloquentQueryBuilder $query */
        $query = EntryFacade::query();

        return $query
            ->where('collection', 'structured_data_templates')
            ->whereStatus('published')
            ->where('blueprint_type', 'runway')
            ->where('use_for_runway', $resourceHandle)
            ->get()
            ->map(fn ($entry): ?string => $entry->id() !== null ? (string) $entry->id() : null)
            ->filter()
            ->values()
            ->all();
    }
}
