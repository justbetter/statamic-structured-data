<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class FieldTransformerFactory
{
    public function getTransformer(?string $type): FieldTransformerInterface
    {
        if ($type === 'replicator_object_array') {
            return new ReplicatorObjectArrayTransformer;
        }

        return new DefaultFieldTransformer;
    }
}
