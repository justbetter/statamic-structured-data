<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class FieldTransformerFactory
{
    public function getTransformer(?string $type): FieldTransformerInterface
    {
        return match ($type) {
            'replicator_object_array' => new ReplicatorObjectArrayTransformer,
            default => new DefaultFieldTransformer,
        };
    }
}
