<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\Transformers\DefaultFieldTransformer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DefaultFieldTransformerTest extends TestCase
{
    #[Test]
    public function it_returns_values_for_array_type_field(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'array',
            'values' => ['value1', 'value2'],
        ];

        $result = $transformer->transform($field);

        $this->assertEquals(['value1', 'value2'], $result);
    }

    #[Test]
    public function it_returns_value_for_object_type_field(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'object',
            'value' => ['key' => 'value'],
        ];

        $result = $transformer->transform($field);

        $this->assertEquals(['key' => 'value'], $result);
    }

    #[Test]
    public function it_returns_values_for_object_array_type_field(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'object_array',
            'values' => [['key1' => 'value1'], ['key2' => 'value2']],
        ];

        $result = $transformer->transform($field);

        $this->assertEquals([['key1' => 'value1'], ['key2' => 'value2']], $result);
    }

    #[Test]
    public function it_returns_float_for_numeric_type_field_with_numeric_value(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'numeric',
            'value' => '123.45',
        ];

        $result = $transformer->transform($field);

        $this->assertEquals(123.45, $result);
        $this->assertIsFloat($result);
    }

    #[Test]
    public function it_returns_original_value_for_numeric_type_field_with_non_numeric_value(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'numeric',
            'value' => 'not a number',
        ];

        $result = $transformer->transform($field);

        $this->assertEquals('not a number', $result);
    }

    #[Test]
    public function it_returns_value_for_field_with_value_key(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'text',
            'value' => 'some text',
        ];

        $result = $transformer->transform($field);

        $this->assertEquals('some text', $result);
    }

    #[Test]
    public function it_returns_null_for_field_without_value_key(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'text',
        ];

        $result = $transformer->transform($field);

        $this->assertNull($result);
    }

    #[Test]
    public function it_returns_null_for_empty_field_array(): void
    {
        $transformer = new DefaultFieldTransformer;

        $result = $transformer->transform([]);

        $this->assertNull($result);
    }

    #[Test]
    public function it_ignores_item_parameter(): void
    {
        $transformer = new DefaultFieldTransformer;

        $field = [
            'type' => 'text',
            'value' => 'test',
        ];

        $item = new \stdClass;

        $result = $transformer->transform($field, $item);

        $this->assertEquals('test', $result);
    }
}
