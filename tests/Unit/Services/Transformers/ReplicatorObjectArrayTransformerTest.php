<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Illuminate\Support\Collection;
use Justbetter\StatamicStructuredData\Services\PreviewContext;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorObjectArrayTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Fields\Value;

class ReplicatorObjectArrayTransformerTest extends TestCase
{
    private function makeTransformer(
        ?ReplicatorRowNormalizer $normalizer = null,
        ?PreviewContext $previewContext = null
    ): ReplicatorObjectArrayTransformer {
        return new ReplicatorObjectArrayTransformer(
            $normalizer ?? new ReplicatorRowNormalizer,
            $previewContext ?? new PreviewContext
        );
    }

    /**
     * @param  array<int|string, mixed>  $data
     */
    private function makeItemWithReplicatorData(array $data): object
    {
        return new class($data)
        {
            /**
             * @param  array<int|string, mixed>  $data
             */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                return $this->data;
            }
        };
    }

    #[Test]
    public function it_returns_empty_array_when_config_is_not_array(): void
    {
        $transformer = $this->makeTransformer();

        $field = [
            'type' => 'replicator_object_array',
            'config' => null,
        ];

        $result = $transformer->transform($field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_replicator_field_is_missing(): void
    {
        $transformer = $this->makeTransformer();

        $field = [
            'type' => 'replicator_object_array',
            'config' => [],
        ];

        $result = $transformer->transform($field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_replicator_field_is_empty_string(): void
    {
        $transformer = $this->makeTransformer();

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => '',
            ],
        ];

        $result = $transformer->transform($field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_item_has_no_replicator_data(): void
    {
        $transformer = $this->makeTransformer();

        $item = new class
        {
            public function get(string $key): mixed
            {
                return null;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_processes_replicator_rows_with_field_mode_mapping(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            [
                'type' => 'test_set',
                'values' => [
                    'title' => 'Test Title',
                    'description' => 'Test Description',
                ],
            ],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                    ['key' => 'desc', 'mode' => 'field', 'field' => 'description'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertArrayHasKey('name', $first);
        $this->assertEquals('Test Title', $first['name']);
        $this->assertArrayHasKey('desc', $first);
        $this->assertEquals('Test Description', $first['desc']);
    }

    #[Test]
    public function it_filters_rows_by_set_filter(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => ['title' => 'Test 1']],
            ['type' => 'other_set', 'values' => ['title' => 'Test 2']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'set' => 'test_set',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertEquals('Test 1', $first['name']);
    }

    #[Test]
    public function it_handles_static_mode_mapping(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => []],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'static_value', 'mode' => 'static', 'static' => 'Static Content'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertEquals('Static Content', $first['static_value']);
    }

    #[Test]
    public function it_handles_nested_replicator_mode(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            [
                'type' => 'test_set',
                'values' => [
                    'nested_field' => [
                        ['type' => 'nested_set', 'values' => ['title' => 'Nested Title']],
                    ],
                ],
            ],
        ];

        $item = new class($replicatorData)
        {
            /**
             * @param  array<int|string, mixed>  $data
             */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                if ($key === 'nested_field') {
                    return [
                        ['type' => 'nested_set', 'values' => ['title' => 'Nested Title']],
                    ];
                }

                return $this->data;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    [
                        'key' => 'nested',
                        'mode' => 'nested_replicator',
                        'nested' => [
                            'replicator_field' => 'nested_field',
                            'mappings' => [
                                ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertArrayHasKey('nested', $first);
        $this->assertIsArray($first['nested']);
    }

    #[Test]
    public function it_unwraps_value_objects(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => ['title' => 'Test']],
        ];

        $valueMock = $this->mock(Value::class, function (MockInterface $mock) use ($replicatorData): void {
            $mock->shouldReceive('value')->andReturn($replicatorData);
        });

        $item = new class($valueMock)
        {
            /** @param mixed $value */
            public function __construct(private $value) {}

            public function get(string $key): mixed
            {
                return $this->value;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_unwraps_collection_objects(): void
    {
        $transformer = $this->makeTransformer();

        $collection = collect([
            ['type' => 'test_set', 'values' => ['title' => 'Test']],
        ]);

        $item = new class($collection)
        {
            /** @param mixed $collection */
            public function __construct(private $collection) {}

            public function get(string $key): mixed
            {
                return $this->collection;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_handles_rows_with_set_key_instead_of_type(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['set' => 'test_set', 'values' => ['title' => 'Test']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'set' => 'test_set',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_maps_rows_with_nested_fields_key(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'fields' => ['title' => 'Nested Fields Title']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
        $this->assertSame('Nested Fields Title', $result[0]['name']);
    }

    #[Test]
    public function it_passthrough_rows_when_mappings_are_empty(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'title' => 'Passthrough Title'],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
        $this->assertSame('Passthrough Title', $result[0]['title']);
    }

    #[Test]
    public function it_handles_rows_without_values_key(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'title' => 'Direct Value'],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertEquals('Direct Value', $first['name']);
    }

    #[Test]
    public function it_skips_mappings_without_key(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => ['title' => 'Test']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['mode' => 'field', 'field' => 'title'],
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertArrayHasKey('name', $first);
        $this->assertArrayNotHasKey('', $first);
    }

    #[Test]
    public function it_skips_mappings_without_field_in_field_mode(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => ['title' => 'Test']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field'],
                    ['key' => 'title', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);

        /** @var array<string, mixed> $first */
        $first = $result[0];

        $this->assertArrayNotHasKey('name', $first);
        $this->assertArrayHasKey('title', $first);
    }

    #[Test]
    public function it_returns_empty_array_when_all_rows_filtered_out(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'other_set', 'values' => ['title' => 'Test']],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'set' => 'test_set',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_skips_empty_mapped_results(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => []],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_skips_rows_that_normalize_to_null(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            'not-an-array',
            ['type' => 'test_set', 'values' => ['field1' => 'value1']],
        ];

        $item = new class($replicatorData)
        {
            /**
             * @param  array<int|string, mixed>  $data
             */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                if ($key === 'replicator_field') {
                    return $this->data;
                }

                return null;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'field1', 'field' => 'field1'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
    }

    #[Test]
    public function it_handles_non_array_row_values(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['type' => 'test_set', 'values' => 'not-an-array'],
        ];

        $item = new class($replicatorData)
        {
            /**
             * @param  array<int|string, mixed>  $data
             */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                if ($key === 'replicator_field') {
                    return $this->data;
                }

                return null;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_item_with_get_method_when_source_data_not_array(): void
    {
        $transformer = $this->makeTransformer();

        $item = new class
        {
            public function get(string $key): mixed
            {
                return [
                    ['type' => 'test_set', 'values' => ['field1' => 'value1']],
                ];
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'field1', 'field' => 'field1'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_returns_empty_array_when_replicator_data_unwraps_to_non_array(): void
    {
        $transformer = $this->makeTransformer();

        $item = new class
        {
            public function get(string $key): mixed
            {
                return 'not-an-array';
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_non_array_values_in_normalize(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('normalizeReplicatorRow');
        $method->setAccessible(true);

        $row = [
            'type' => 'test_set',
            'values' => 'not-an-array',
        ];

        $result = $method->invoke($transformer, $row);

        /** @var array<string, mixed> $result */
        $this->assertIsArray($result['values']);
    }

    #[Test]
    public function it_handles_value_instance_in_normalize(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('normalizeReplicatorRow');
        $method->setAccessible(true);

        $valueMock = $this->mock(Value::class, function ($mock) {
            $mock->shouldReceive('value')->andReturn(['type' => 'test_set', 'values' => ['field1' => 'value1']]);
        });

        $result = $method->invoke($transformer, $valueMock);

        /** @var array<string, mixed> $result */
        $this->assertEquals('test_set', $result['set']);
    }

    #[Test]
    public function it_handles_collection_instance_in_normalize(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('normalizeReplicatorRow');
        $method->setAccessible(true);

        $collectionMock = $this->mock(Collection::class, function ($mock) {
            $mock->shouldReceive('all')->andReturn(['type' => 'test_set', 'values' => ['field1' => 'value1']]);
        });

        $result = $method->invoke($transformer, $collectionMock);

        /** @var array<string, mixed> $result */
        $this->assertEquals('test_set', $result['set']);
    }

    #[Test]
    public function it_handles_non_array_config_in_transform_nested(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('transformNested');
        $method->setAccessible(true);

        $field = [
            'type' => 'replicator_object_array',
            'config' => 'not-an-array',
        ];

        $result = $method->invoke($transformer, $field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_non_string_replicator_handle_in_transform_nested(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('transformNested');
        $method->setAccessible(true);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 123,
            ],
        ];

        $result = $method->invoke($transformer, $field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_empty_string_replicator_handle_in_transform_nested(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('transformNested');
        $method->setAccessible(true);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => '',
            ],
        ];

        $result = $method->invoke($transformer, $field);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_handles_item_get_method_when_source_data_not_array_in_transform_nested(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('transformNested');
        $method->setAccessible(true);

        $replicatorData = [
            ['type' => 'test_set', 'values' => ['field1' => 'value1']],
        ];

        $item = new class($replicatorData)
        {
            /** @param array<int|string, mixed> $data */
            public function __construct(private array $data) {}

            public function get(string $key): mixed
            {
                if ($key === 'replicator_field') {
                    return $this->data;
                }

                return null;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [
                    ['key' => 'field1', 'field' => 'field1'],
                ],
            ],
        ];

        $result = $method->invoke($transformer, $field, $item, null);

        $this->assertNotEmpty($result);
    }

    #[Test]
    public function it_handles_non_array_replicator_data_after_unwrap_in_transform_nested(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('transformNested');
        $method->setAccessible(true);

        $item = new class
        {
            public function get(string $key): mixed
            {
                if ($key === 'replicator_field') {
                    return 'not-an-array';
                }

                return null;
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'mappings' => [],
            ],
        ];

        $result = $method->invoke($transformer, $field, $item);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_uses_flat_transformer_when_flat_mode_is_enabled(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            [
                'type' => 'set',
                'values' => [
                    'flat_key' => 'foo',
                    'flat_value' => 'bar',
                ],
            ],
        ];

        $item = $this->makeItemWithReplicatorData($replicatorData);

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'replicator_field',
                'flat' => true,
                'flat_key_field' => 'flat_key',
                'flat_value_field' => 'flat_value',
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertSame(['foo' => 'bar'], $result);
    }

    #[Test]
    public function unwrap_value_helper_delegates_to_normalizer(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('unwrapValue');
        $method->setAccessible(true);

        $valueMock = $this->mock(Value::class, function (MockInterface $mock): void {
            $mock->shouldReceive('value')->andReturn('unwrapped');
        });

        $result = $method->invoke($transformer, $valueMock);

        $this->assertSame('unwrapped', $result);
    }

    #[Test]
    public function it_handles_non_array_row_values_through_ensure_array_values(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('ensureArrayValues');
        $method->setAccessible(true);

        $result = $method->invoke($transformer, 'not-an-array');

        $this->assertEmpty($result);
    }

    #[Test]
    public function ensure_array_values_returns_array_as_is(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new \ReflectionClass($transformer);
        $method = $reflection->getMethod('ensureArrayValues');
        $method->setAccessible(true);

        $input = ['key' => 'value'];
        $result = $method->invoke($transformer, $input);

        $this->assertEquals($input, $result);
    }
}
