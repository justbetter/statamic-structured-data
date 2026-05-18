<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Support\Collection;

class ReplicatorHandleDiscovery
{
    public function __construct(
        protected ReplicatorRowNormalizer $normalizer
    ) {}

    /**
     * @param  mixed  $item
     * @return array<int, string>
     */
    public function handlesWithData($item): array
    {
        if (! is_object($item) || ! method_exists($item, 'blueprint')) {
            return [];
        }

        try {
            $blueprint = $item->blueprint();
        } catch (\Throwable) {
            return [];
        }

        if (! $blueprint) {
            return [];
        }

        $handles = [];

        foreach ($blueprint->fields()->all() as $field) {
            if (! is_object($field) || ! method_exists($field, 'type') || ! method_exists($field, 'handle')) {
                continue;
            }

            if ($field->type() !== 'replicator') {
                continue;
            }

            $handle = $field->handle();
            $raw = null;

            if (method_exists($item, 'data')) {
                $data = $item->data();

                if ($data instanceof Collection) {
                    $raw = $data->get($handle);
                }
            }

            if (($raw === null || $raw === [] || $raw === '') && method_exists($item, 'get')) {
                $raw = $item->get($handle);
            }

            $raw = $this->normalizer->decodeReplicatorData($raw);

            if (is_array($raw) && $raw !== []) {
                $handles[] = $handle;
            }
        }

        return $handles;
    }

    /**
     * @param  mixed  $item
     */
    public function infer($item, ?string $preferred = null): ?string
    {
        $handles = $this->handlesWithData($item);

        if ($preferred && in_array($preferred, $handles, true)) {
            return $preferred;
        }

        if (count($handles) === 1) {
            return $handles[0];
        }

        if ($preferred && $preferred !== '') {
            return $preferred;
        }

        return $handles[0] ?? null;
    }
}
