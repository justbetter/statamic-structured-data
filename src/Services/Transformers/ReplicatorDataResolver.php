<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Services\PreviewContext;
use Statamic\Contracts\Data\Augmentable;

class ReplicatorDataResolver
{
    public function __construct(
        protected ReplicatorRowNormalizer $normalizer,
        protected PreviewContext $previewContext,
        protected ?StatamicReplicatorLoader $statamicLoader = null,
        protected ?ReplicatorHandleDiscovery $handleDiscovery = null
    ) {
        $this->statamicLoader ??= new StatamicReplicatorLoader;
        $this->handleDiscovery ??= new ReplicatorHandleDiscovery($this->normalizer);
    }

    /**
     * @param  mixed  $item
     */
    public function resolve($item, ?string $handle): mixed
    {
        foreach ($this->handlesToTry($item, $handle) as $tryHandle) {
            $resolved = $this->resolveHandle($item, $tryHandle);

            if ($this->hasReplicatorContent($resolved)) {
                return $resolved;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $item
     * @return array<int, string>
     */
    protected function handlesToTry($item, ?string $handle): array
    {
        $handles = [];

        if (is_string($handle) && $handle !== '') {
            $handles[] = $handle;
        }

        foreach ($this->handleDiscovery->handlesWithData($item) as $discovered) {
            if (! in_array($discovered, $handles, true)) {
                $handles[] = $discovered;
            }
        }

        return $handles;
    }

    /**
     * @param  mixed  $item
     */
    protected function resolveHandle($item, string $handle): mixed
    {
        $previewValues = $this->previewContext->getValues();

        if (is_array($previewValues) && array_key_exists($handle, $previewValues)) {
            $previewData = $this->normalizer->decodeReplicatorData($previewValues[$handle]);

            if ($this->hasReplicatorContent($previewData)) {
                return $previewData;
            }
        }

        $statamicData = $this->statamicLoader->load($item, $handle);
        $statamicData = $this->normalizer->decodeReplicatorData($statamicData);

        if ($this->hasReplicatorContent($statamicData)) {
            return $statamicData;
        }

        foreach ($this->entrySources($item, $handle) as $candidate) {
            $candidate = $this->normalizer->decodeReplicatorData($candidate);

            if ($this->hasReplicatorContent($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  mixed  $item
     * @return array<int, mixed>
     */
    protected function entrySources($item, string $handle): array
    {
        if (! is_object($item)) {
            return [];
        }

        $sources = [];

        if (method_exists($item, 'data')) {
            $data = $item->data();

            if ($data instanceof Collection && $data->has($handle)) {
                $sources[] = $data->get($handle);
            }
        }

        if (method_exists($item, 'get')) {
            $sources[] = $item->get($handle);
        }

        if (method_exists($item, 'value')) {
            $sources[] = $item->value($handle);
        }

        if (method_exists($item, 'values')) {
            $values = $item->values();

            if (is_object($values) && method_exists($values, 'get')) {
                $sources[] = $values->get($handle);
            }
        }

        if ($item instanceof Augmentable) {
            $augmented = $item->toAugmentedArray();

            if (array_key_exists($handle, $augmented)) {
                $sources[] = $augmented[$handle];
            }
        }

        if (method_exists($item, 'origin')) {
            $origin = $item->origin();

            if ($origin && $origin !== $item) {
                foreach ($this->entrySources($origin, $handle) as $originSource) {
                    $sources[] = $originSource;
                }
            }
        }

        return $sources;
    }

    protected function hasReplicatorContent(mixed $data): bool
    {
        $data = $this->normalizer->unwrap($data);

        if ($data === null || $data === '' || $data === []) {
            return false;
        }

        return is_array($data);
    }
}
