<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\PreviewContext;

class ReplicatorObjectArrayTransformer implements FieldTransformerInterface
{
    protected ReplicatorMappedTransformer $mappedTransformer;

    protected ReplicatorFlatTransformer $flatTransformer;

    protected ReplicatorDataResolver $resolver;

    protected ReplicatorConfigNormalizer $configNormalizer;

    protected ReplicatorHandleDiscovery $handleDiscovery;

    public function __construct(
        protected ReplicatorRowNormalizer $normalizer,
        protected PreviewContext $previewContext,
        ?ReplicatorDataResolver $resolver = null,
        ?ReplicatorConfigNormalizer $configNormalizer = null,
        ?ReplicatorHandleDiscovery $handleDiscovery = null
    ) {
        $this->handleDiscovery = $handleDiscovery ?? new ReplicatorHandleDiscovery($this->normalizer);
        $this->resolver = $resolver ?? new ReplicatorDataResolver($this->normalizer, $this->previewContext);
        $this->configNormalizer = $configNormalizer ?? new ReplicatorConfigNormalizer;
        $this->mappedTransformer = new ReplicatorMappedTransformer($this->normalizer);
        $this->flatTransformer = new ReplicatorFlatTransformer($this->normalizer);
    }

    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @return array<int, array<string, mixed>>|array<string, mixed>
     */
    public function transform(array $field, $item = null): array
    {
        $config = $this->configNormalizer->normalizeFieldConfig($field);

        if ($config === null) {
            return [];
        }

        $replicatorHandle = $config['replicator_field'] !== ''
            ? $config['replicator_field']
            : ($this->handleDiscovery->infer($item) ?? '');

        if ($replicatorHandle === '') {
            return [];
        }

        $setFilter = $config['set'] !== '' ? $config['set'] : null;
        /** @var array<int, array<string, mixed>> $mappings */
        $mappings = $config['mappings'];
        $flat = ($config['flat'] ?? false) === true;
        $flatKeyField = $config['flat_key_field'] !== '' ? $config['flat_key_field'] : null;
        $flatValueField = $config['flat_value_field'] !== '' ? $config['flat_value_field'] : null;

        $replicatorData = $this->resolver->resolve($item, $replicatorHandle);
        $replicatorData = $this->normalizer->decodeReplicatorData($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        $replicatorData = $this->normalizeReplicatorRows($replicatorData);

        if ($flat && $flatKeyField && $flatValueField) {
            return $this->flatTransformer->transform($replicatorData, $setFilter, $flatKeyField, $flatValueField);
        }

        if ($mappings === []) {
            return $this->passthroughRows($replicatorData, $setFilter);
        }

        return $this->mappedTransformer->transform($replicatorData, $setFilter, $mappings, $item);
    }

    /**
     * @param  array<int, mixed>  $replicatorData
     * @return array<int, array<string, mixed>>
     */
    protected function passthroughRows(array $replicatorData, ?string $setFilter = null): array
    {
        $results = [];

        foreach ($replicatorData as $row) {
            $rowArray = $this->normalizer->normalize($row);

            if (! $rowArray) {
                continue;
            }

            $rowSet = $rowArray['set'] ?? null;

            if (is_string($setFilter) && $setFilter !== '' && strtolower((string) $rowSet) !== strtolower($setFilter)) {
                continue;
            }

            $values = $rowArray['values'];

            if (is_array($values) && $values !== []) {
                $results[] = $values;
            }
        }

        return $results;
    }

    /**
     * @param  array<int|string, mixed>  $data
     * @return array<int, mixed>
     */
    protected function normalizeReplicatorRows(array $data): array
    {
        if (isset($data['type']) || isset($data['set'])) {
            return [$data];
        }

        if (! array_is_list($data)) {
            return array_values($data);
        }

        return $data;
    }
}
