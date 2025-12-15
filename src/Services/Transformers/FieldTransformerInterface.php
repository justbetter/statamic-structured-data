<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

interface FieldTransformerInterface
{
    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @return mixed
     */
    public function transform(array $field, $item = null);
}
