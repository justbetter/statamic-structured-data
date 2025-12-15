<?php

namespace Justbetter\StatamicStructuredData\Services\Transformers;

class DefaultFieldTransformer implements FieldTransformerInterface
{
    /**
     * @param  array<string, mixed>  $field
     * @param  mixed  $item
     * @return mixed
     */
    public function transform(array $field, $item = null)
    {
        $type = $field['type'] ?? null;

        if ($type === 'array' && isset($field['values'])) {
            return $field['values'];
        }

        if ($type === 'object' && isset($field['value'])) {
            return $field['value'];
        }

        if ($type === 'object_array' && isset($field['values'])) {
            return $field['values'];
        }

        if ($type === 'numeric' && isset($field['value'])) {
            $value = $field['value'];
            if (is_numeric($value)) {
                return (float) $value;
            }
        }

        return $field['value'] ?? null;
    }
}
