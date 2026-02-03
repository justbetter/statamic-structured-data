<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class ReplicatorObjectArrayTransformer implements FieldTransformerInterface
{
    protected ReplicatorRowNormalizer $normalizer;

    protected ReplicatorMappedTransformer $mappedTransformer;

    protected ReplicatorFlatTransformer $flatTransformer;

    public function __construct()
    {
        $this->normalizer = new ReplicatorRowNormalizer;
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
        /** @var array<string, mixed>|null $config */
        $config = $field['config'] ?? null;

        if (! is_array($config)) {
            return [];
        }

        $replicatorHandle = $config['replicator_field'] ?? null;

        if (! is_string($replicatorHandle) || $replicatorHandle === '') {
            return [];
        }

        $setFilter = is_string($config['set'] ?? null) ? $config['set'] : null;
        $mappings = is_array($config['mappings'] ?? null) ? $config['mappings'] : [];
        $flat = isset($config['flat']) && $config['flat'] === true;
        $flatKeyField = is_string($config['flat_key_field'] ?? null) ? $config['flat_key_field'] : null;
        $flatValueField = is_string($config['flat_value_field'] ?? null) ? $config['flat_value_field'] : null;

        $replicatorData = null;

        if (is_object($item) && method_exists($item, 'get')) {
            $replicatorData = $item->get($replicatorHandle);
        }

        $replicatorData = $this->normalizer->unwrap($replicatorData);

        if (! is_array($replicatorData)) {
            return [];
        }

        if ($flat && $flatKeyField && $flatValueField) {
            return $this->flatTransformer->transform($replicatorData, $setFilter, $flatKeyField, $flatValueField);
        }

        return $this->mappedTransformer->transform($replicatorData, $setFilter, $mappings, $item);
    }
}
