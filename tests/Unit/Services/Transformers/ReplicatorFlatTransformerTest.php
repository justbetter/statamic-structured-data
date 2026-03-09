<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorFlatTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class ReplicatorFlatTransformerTest extends TestCase
{
    private function makeTransformer(?ReplicatorRowNormalizer $normalizer = null): ReplicatorFlatTransformer
    {
        return new ReplicatorFlatTransformer($normalizer ?? new ReplicatorRowNormalizer);
    }

    private function invokeProtectedMethod(string $method, ?ReplicatorRowNormalizer $normalizer, mixed ...$args): mixed
    {
        $transformer = $this->makeTransformer($normalizer);
        $reflection = new ReflectionClass($transformer);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($transformer, $args);
    }

    #[Test]
    public function it_transforms_rows_with_direct_key_and_value(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            ['key_field' => 'first', 'value_field' => 'First Value'],
            ['key_field' => 'second', 'value_field' => 'Second Value'],
        ];

        $result = $transformer->transform($replicatorData, null, 'key_field', 'value_field');

        $this->assertSame([
            'first' => 'First Value',
            'second' => 'Second Value',
        ], $result);
    }

    #[Test]
    public function it_transforms_nested_replicator_rows(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            [
                'type' => 'outer',
                'values' => [
                    'nested' => [
                        [
                            'type' => 'inner',
                            'values' => [
                                'key_field' => 'nested-key',
                                'value_field' => 'nested-value',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $result = $transformer->transform($replicatorData, null, 'key_field', 'value_field');

        $this->assertSame(['nested-key' => 'nested-value'], $result);
    }

    #[Test]
    public function it_skips_rows_by_set_filter(): void
    {
        $transformer = $this->makeTransformer();

        $replicatorData = [
            [
                'type' => 'included',
                'values' => [
                    'key_field' => 'included-key',
                    'value_field' => 'included-value',
                ],
            ],
            [
                'type' => 'excluded',
                'values' => [
                    'key_field' => 'excluded-key',
                    'value_field' => 'excluded-value',
                ],
            ],
        ];

        $result = $transformer->transform($replicatorData, 'included', 'key_field', 'value_field');

        $this->assertSame(['included-key' => 'included-value'], $result);
    }

    #[Test]
    public function extract_flat_data_from_row_handles_non_array_row(): void
    {
        $result = $this->invokeProtectedMethod(
            'extractFlatDataFromRow',
            null,
            'not-an-array',
            null,
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_flat_data_from_row_normalizes_non_string_set_value(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            public function normalize($row): ?array
            {
                if ($row === null) {
                    return null;
                }

                return [
                    'set' => 123,
                    'values' => [
                        'key_field' => 'k',
                        'value_field' => 'v',
                    ],
                ];
            }
        };

        $row = ['other' => 'value'];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFlatDataFromRow',
            $normalizer,
            $row,
            null,
            'key_field',
            'value_field'
        );

        $this->assertSame(['k' => 'v'], $result);
    }

    #[Test]
    public function extract_flat_data_from_row_uses_fallback_to_original_row_when_values_missing(): void
    {
        $row = [
            'type' => 'set',
            'values' => [],
            'key_field' => 'fallback-key',
            'value_field' => 'fallback-value',
        ];

        $result = $this->invokeProtectedMethod(
            'extractFlatDataFromRow',
            null,
            $row,
            null,
            'key_field',
            'value_field'
        );

        $this->assertSame(['fallback-key' => 'fallback-value'], $result);
    }

    #[Test]
    public function get_replicator_rows_to_process_handles_various_shapes(): void
    {
        $rowsArray = [
            ['type' => 'row', 'values' => []],
        ];
        $singleRow = ['type' => 'row', 'values' => []];
        $nonReplicator = ['foo' => 'bar'];

        $this->assertSame($rowsArray, $this->invokeProtectedMethod('getReplicatorRowsToProcess', null, $rowsArray));
        $this->assertSame([$singleRow], $this->invokeProtectedMethod('getReplicatorRowsToProcess', null, $singleRow));
        $this->assertNull($this->invokeProtectedMethod('getReplicatorRowsToProcess', null, $nonReplicator));
    }

    #[Test]
    public function search_recursively_for_fields_finds_key_in_associative_array(): void
    {
        $data = [
            'key_field' => 'assoc-key',
            'value_field' => 'assoc-value',
        ];

        $result = $this->invokeProtectedMethod('searchRecursivelyForFields', null, $data, 'key_field', 'value_field');

        $this->assertSame(['assoc-key' => 'assoc-value'], $result);
    }

    #[Test]
    public function search_recursively_for_fields_returns_empty_for_non_array(): void
    {
        $result = $this->invokeProtectedMethod(
            'searchRecursivelyForFields',
            null,
            'not-an-array',
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function search_recursively_for_fields_finds_key_in_indexed_array(): void
    {
        $data = [
            ['key_field' => 'first', 'value_field' => 'First'],
            ['key_field' => 'second', 'value_field' => 'Second'],
        ];

        $result = $this->invokeProtectedMethod('searchRecursivelyForFields', null, $data, 'key_field', 'value_field');

        $this->assertSame([
            'first' => 'First',
            'second' => 'Second',
        ], $result);
    }

    #[Test]
    public function search_in_associative_array_recurses_for_non_replicator_values(): void
    {
        $data = [
            'level1' => [
                'key_field' => 'deep-key',
                'value_field' => 'deep-value',
            ],
        ];

        $result = $this->invokeProtectedMethod('searchInAssociativeArray', null, $data, 'key_field', 'value_field');

        $this->assertSame(['deep-key' => 'deep-value'], $result);
    }

    #[Test]
    public function search_in_associative_array_uses_extract_from_single_replicator_row_for_replicator_values(): void
    {
        $data = [
            'replicator' => [
                'type' => 'set',
                'values' => [
                    'key_field' => 'rep-key',
                    'value_field' => 'rep-value',
                ],
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod('searchInAssociativeArray', null, $data, 'key_field', 'value_field');

        $this->assertSame(['rep-key' => 'rep-value'], $result);
    }

    #[Test]
    public function extract_from_single_replicator_row_returns_empty_when_normalize_fails(): void
    {
        $result = $this->invokeProtectedMethod(
            'extractFromSingleReplicatorRow',
            null,
            'not-an-array',
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_fields_from_nested_replicators_handles_non_replicator_nested_arrays(): void
    {
        $rowValues = [
            'non_replicator' => [
                'foo' => 'bar',
            ],
        ];

        $result = $this->invokeProtectedMethod(
            'extractFieldsFromNestedReplicators',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_fields_from_nested_replicators_merges_nested_results_when_no_rows_to_process(): void
    {
        $rowValues = [
            'non_replicator' => [
                'key_field' => 'nested-key',
                'value_field' => 'nested-value',
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFieldsFromNestedReplicators',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame(['nested-key' => 'nested-value'], $result);
    }

    #[Test]
    public function extract_from_nested_structures_returns_nested_data_when_available(): void
    {
        $rowValues = [
            'nested' => [
                [
                    'type' => 'set',
                    'values' => [
                        'key_field' => 'first',
                        'value_field' => 'First',
                    ],
                ],
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFromNestedStructures',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame(['first' => 'First'], $result);
    }

    #[Test]
    public function extract_from_nested_structures_returns_recursive_data_when_no_nested_data(): void
    {
        $rowValues = [
            'key_field' => 'k',
            'value_field' => 'v',
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFromNestedStructures',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame(['k' => 'v'], $result);
    }

    #[Test]
    public function extract_from_nested_structures_uses_extract_from_row_values_as_last_resort(): void
    {
        $rowValues = [
            'foo' => [
                'bar' => 'baz',
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFromNestedStructures',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_from_row_values_skips_non_array_unwrapped_values(): void
    {
        $rowValues = [
            'scalar' => 'value',
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFromRowValues',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_from_single_replicator_row_uses_recursive_search_when_key_missing(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            public function normalize($row): ?array
            {
                if ($row === null) {
                    return null;
                }

                return [
                    'set' => 'set',
                    'values' => [
                        'nested' => [
                            'key_field' => 'deep',
                            'value_field' => 'Deep',
                        ],
                    ],
                ];
            }
        };

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFromSingleReplicatorRow',
            $normalizer,
            ['dummy' => 'value'],
            'key_field',
            'value_field'
        );

        $this->assertSame(['deep' => 'Deep'], $result);
    }

    #[Test]
    public function extract_fields_from_nested_replicators_skips_non_array_unwrapped_values(): void
    {
        $rowValues = [
            'scalar' => 'value',
        ];

        /** @var array<string, mixed> $result */
        $result = $this->invokeProtectedMethod(
            'extractFieldsFromNestedReplicators',
            null,
            $rowValues,
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function extract_from_normalized_row_falls_back_to_recursive_search_when_no_key(): void
    {
        $transformer = new ReplicatorFlatTransformer(new ReplicatorRowNormalizer);
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('extractFromNormalizedRow');
        $method->setAccessible(true);

        $row = [
            'type' => 'row',
            'values' => [
                'nested' => [
                    'key_field' => 'nested-key',
                    'value_field' => 'nested-value',
                ],
            ],
        ];

        $result = $method->invoke($transformer, $row, 'key_field', 'value_field');

        $this->assertSame(['nested-key' => 'nested-value'], $result);
    }

    #[Test]
    public function extract_from_row_values_and_unwrapped_value_handle_replicator_rows_array(): void
    {
        $transformer = new ReplicatorFlatTransformer(new ReplicatorRowNormalizer);
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('extractFromRowValues');
        $method->setAccessible(true);

        $rowValues = [
            'replicator' => [
                [
                    'type' => 'set',
                    'values' => [
                        'key_field' => 'first',
                        'value_field' => 'First',
                    ],
                ],
                [
                    'type' => 'set',
                    'values' => [
                        'key_field' => 'second',
                        'value_field' => 'Second',
                    ],
                ],
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $method->invoke($transformer, $rowValues, 'key_field', 'value_field');

        $this->assertSame([
            'first' => 'First',
            'second' => 'Second',
        ], $result);
    }

    #[Test]
    public function extract_from_unwrapped_value_handles_single_replicator_row_and_recursive_arrays(): void
    {
        $transformer = new ReplicatorFlatTransformer(new ReplicatorRowNormalizer);
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('extractFromUnwrappedValue');
        $method->setAccessible(true);

        $singleRow = [
            'type' => 'set',
            'values' => [
                'key_field' => 'single',
                'value_field' => 'Single',
            ],
        ];

        /** @var array<string, mixed> $singleResult */
        $singleResult = $method->invoke($transformer, $singleRow, 'key_field', 'value_field');
        $this->assertSame(['single' => 'Single'], $singleResult);

        $recursiveArray = [
            'nested' => [
                'key_field' => 'deep',
                'value_field' => 'Deep',
            ],
        ];

        /** @var array<string, mixed> $recursiveResult */
        $recursiveResult = $method->invoke($transformer, $recursiveArray, 'key_field', 'value_field');
        $this->assertSame(['deep' => 'Deep'], $recursiveResult);
    }

    #[Test]
    public function extract_from_single_replicator_row_returns_empty_when_values_not_array(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            public function normalize($row): ?array
            {
                if ($row === null) {
                    return null;
                }

                return [
                    'set' => 'set',
                    'values' => 'not-an-array',
                ];
            }
        };

        $transformer = new ReplicatorFlatTransformer($normalizer);
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('extractFromSingleReplicatorRow');
        $method->setAccessible(true);

        $result = $method->invoke($transformer, ['dummy' => 'value'], 'key_field', 'value_field');

        $this->assertSame([], $result);
    }

    #[Test]
    public function search_in_indexed_array_skips_non_array_unwrapped_rows(): void
    {
        $transformer = new ReplicatorFlatTransformer(new ReplicatorRowNormalizer);
        $reflection = new ReflectionClass($transformer);
        $method = $reflection->getMethod('searchInIndexedArray');
        $method->setAccessible(true);

        $data = [
            'scalar',
            [
                'key_field' => 'key',
                'value_field' => 'value',
            ],
        ];

        /** @var array<string, mixed> $result */
        $result = $method->invoke($transformer, $data, 'key_field', 'value_field');

        $this->assertSame(['key' => 'value'], $result);
    }

    #[Test]
    public function extract_from_normalized_row_falls_back_when_normalize_returns_null(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            public function normalize($row): ?array
            {
                return null;
            }
        };

        $rowWithKey = [
            'key_field' => 'k',
            'value_field' => 'v',
        ];

        $result = $this->invokeProtectedMethod(
            'extractFromNormalizedRow',
            $normalizer,
            $rowWithKey,
            'key_field',
            'value_field'
        );

        $this->assertSame(['k' => 'v'], $result);
    }

    #[Test]
    public function extract_from_normalized_row_returns_empty_when_values_not_array(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            public function normalize($row): ?array
            {
                if ($row === null) {
                    return null;
                }

                return [
                    'set' => 'set',
                    'values' => 'not-an-array',
                ];
            }
        };

        $result = $this->invokeProtectedMethod(
            'extractFromNormalizedRow',
            $normalizer,
            ['dummy' => 'value'],
            'key_field',
            'value_field'
        );

        $this->assertSame([], $result);
    }

    #[Test]
    public function helper_methods_behave_as_expected(): void
    {
        $transformer = $this->makeTransformer();
        $reflection = new ReflectionClass($transformer);

        $isIndexedArrayMethod = $reflection->getMethod('isIndexedArray');
        $isIndexedArrayMethod->setAccessible(true);

        $isValidKeyMethod = $reflection->getMethod('isValidKey');
        $isValidKeyMethod->setAccessible(true);

        $this->assertTrue($isIndexedArrayMethod->invoke($transformer, [1, 2, 3]));
        $this->assertFalse($isIndexedArrayMethod->invoke($transformer, ['a' => 1]));

        $this->assertTrue($isValidKeyMethod->invoke($transformer, 'key'));
        $this->assertTrue($isValidKeyMethod->invoke($transformer, 123));
        $this->assertFalse($isValidKeyMethod->invoke($transformer, null));
        $this->assertFalse($isValidKeyMethod->invoke($transformer, ''));
    }
}
