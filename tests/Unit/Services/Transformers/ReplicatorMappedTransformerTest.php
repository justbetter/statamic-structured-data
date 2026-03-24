<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorMappedTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReplicatorMappedTransformerTest extends TestCase
{
    #[Test]
    public function it_handles_non_array_row_values_from_normalizer(): void
    {
        $normalizer = new class extends ReplicatorRowNormalizer
        {
            /** @return array<string, mixed> */
            public function normalize($row): array
            {
                return [
                    'set' => 'test_set',
                    'values' => 'not-an-array',
                ];
            }
        };

        $transformer = new ReplicatorMappedTransformer($normalizer);

        $replicatorData = [
            ['dummy' => 'value'],
        ];

        $mappings = [
            ['key' => 'name', 'mode' => 'static', 'static' => 'Test'],
        ];

        $result = $transformer->transform($replicatorData, null, $mappings, null);

        $this->assertCount(1, $result);
        $this->assertSame('Test', $result[0]['name']);
    }
}
