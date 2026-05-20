<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class FieldTransformerFactory
{
    public function getTransformer(?string $type): FieldTransformerInterface
    {
        if ($type === 'replicator_object_array') {
            return app(ReplicatorObjectArrayTransformer::class);
        }

        return new DefaultFieldTransformer;
    }
}
