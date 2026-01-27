<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class DefaultFieldTransformer implements FieldTransformerInterface
{
    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     */
    public function transform(array $field, $item = null): mixed
    {
        $type = $field['type'] ?? null;

        return match ($type) {
            'array' => isset($field['values']) ? $field['values'] : null,
            'object' => isset($field['value']) ? $field['value'] : null,
            'object_array' => isset($field['values']) ? $field['values'] : null,
            'numeric' => isset($field['value']) && is_numeric($field['value']) ? (float) $field['value'] : null,
            default => $field['value'] ?? null,
        };
    }
}
