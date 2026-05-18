<?php

namespace Justbetter\StatamicStructuredData\Services;

use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorConfigNormalizer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorDataResolver;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorHandleDiscovery;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Statamic\Contracts\Entries\Entry as EntryContract;
use Statamic\Contracts\Taxonomies\Term as TermContract;

class ReplicatorPreviewDiagnostic
{
    public function __construct(
        protected ReplicatorConfigNormalizer $configNormalizer = new ReplicatorConfigNormalizer,
        protected ReplicatorRowNormalizer $rowNormalizer = new ReplicatorRowNormalizer,
    ) {}

    /**
     * @param  mixed  $schemas
     * @return array<int, array<string, mixed>>
     */
    public function forSchemas($schemas, EntryContract|TermContract $contentEntry): array
    {
        if (! is_array($schemas)) {
            return [];
        }

        $resolver = app(ReplicatorDataResolver::class);
        $discovery = app(ReplicatorHandleDiscovery::class);
        $diagnostics = [];

        foreach ($schemas as $schemaIndex => $schema) {
            if (! is_array($schema) || ! isset($schema['fields']) || ! is_array($schema['fields'])) {
                continue;
            }

            foreach ($schema['fields'] as $fieldIndex => $field) {
                if (! is_array($field) || ! $this->isReplicatorObjectArrayField($field)) {
                    continue;
                }

                $config = $this->configNormalizer->normalizeFieldConfig($field);
                $handle = is_array($config) ? ($config['replicator_field'] ?? '') : '';
                $inferredHandle = $discovery->infer($contentEntry, $handle !== '' ? $handle : null);
                $rawData = $resolver->resolve($contentEntry, $handle !== '' ? $handle : $inferredHandle);
                $rowCount = is_array($rawData) ? count($rawData) : 0;

                $diagnostics[] = [
                    'schema_index' => $schemaIndex,
                    'field_index' => $fieldIndex,
                    'field_key' => $field['key'] ?? null,
                    'replicator_field' => $handle,
                    'inferred_replicator_field' => $inferredHandle,
                    'discovered_handles' => $discovery->handlesWithData($contentEntry),
                    'mapping_count' => is_array($config) ? count($config['mappings']) : 0,
                    'mappings' => is_array($config) ? $config['mappings'] : [],
                    'set_filter' => is_array($config) ? ($config['set'] ?? '') : '',
                    'row_count' => $rowCount,
                    'raw_config' => $field['config'] ?? null,
                ];
            }
        }

        return $diagnostics;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    protected function isReplicatorObjectArrayField(array $field): bool
    {
        $type = $field['type'] ?? null;

        if (is_string($type)) {
            return $type === 'replicator_object_array';
        }

        if (is_array($type) && is_string($type['value'] ?? null)) {
            return $type['value'] === 'replicator_object_array';
        }

        return false;
    }
}
