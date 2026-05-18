<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Statamic\Fieldtypes\Replicator;

class StatamicReplicatorLoader
{
    public function __construct(
        protected ReplicatorRowNormalizer $normalizer = new ReplicatorRowNormalizer
    ) {}

    /**
     * @param  mixed  $item
     * @return array<int, mixed>|null
     */
    public function load($item, string $handle): ?array
    {
        if (! is_object($item) || ! method_exists($item, 'blueprint')) {
            return null;
        }

        try {
            $blueprint = $item->blueprint();
        } catch (\Throwable) {
            return null;
        }

        $raw = $this->rawValue($item, $handle);

        if (! $this->hasContent($raw)) {
            return null;
        }

        if (! $blueprint || ! method_exists($blueprint, 'hasField') || ! $blueprint->hasField($handle)) {
            return $this->normalizeRaw($raw);
        }

        if (! method_exists($blueprint, 'field')) {
            return $this->normalizeRaw($raw);
        }

        $field = $blueprint->field($handle);

        if (! is_object($field) || ! method_exists($field, 'type') || $field->type() !== 'replicator') {
            return $this->normalizeRaw($raw);
        }

        if (! method_exists($field, 'setParent') || ! method_exists($field, 'setValue') || ! method_exists($field, 'fieldtype')) {
            return $this->normalizeRaw($raw);
        }

        $field = $field->setParent($item)->setValue($raw);
        $fieldtype = $field->fieldtype();

        if (! $fieldtype instanceof Replicator) {
            return $this->normalizeRaw($raw);
        }

        try {
            $processed = $fieldtype->preProcess($field->value());
        } catch (\Throwable) {
            return $this->normalizeRaw($raw);
        }

        if ($this->hasContent($processed)) {
            return is_array($processed) ? $processed : null;
        }

        return $this->normalizeRaw($raw);
    }

    /**
     * @param  mixed  $item
     */
    protected function rawValue($item, string $handle): mixed
    {
        $raw = null;

        if (method_exists($item, 'data')) {
            $raw = $item->data()->get($handle);
        }

        if (! $this->hasContent($raw) && method_exists($item, 'get')) {
            $raw = $item->get($handle);
        }

        return $raw;
    }

    protected function hasContent(mixed $value): bool
    {
        $value = $this->normalizer->unwrap($value);

        return is_array($value) && $value !== [];
    }

    /**
     * @return array<int, mixed>|null
     */
    protected function normalizeRaw(mixed $raw): ?array
    {
        $raw = $this->normalizer->decodeReplicatorData($raw);

        if (! is_array($raw)) {
            return null;
        }

        if (! array_is_list($raw)) {
            return array_values($raw);
        }

        return $raw;
    }
}
