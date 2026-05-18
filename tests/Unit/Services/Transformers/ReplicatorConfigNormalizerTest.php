<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\PreviewContext;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorConfigNormalizer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorObjectArrayTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReplicatorConfigNormalizerTest extends TestCase
{
    #[Test]
    public function it_reads_config_from_value_when_config_key_is_missing(): void
    {
        $transformer = new ReplicatorObjectArrayTransformer(
            new ReplicatorRowNormalizer,
            new PreviewContext
        );

        $item = new class
        {
            public function get(string $key): mixed
            {
                return [
                    ['type' => 'test_set', 'title' => 'Mapped Title'],
                ];
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'value' => [
                'replicator_field' => 'blocks',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
        $this->assertSame('Mapped Title', $result[0]['name']);
    }

    #[Test]
    public function it_normalizes_v_select_style_mapping_values(): void
    {
        $normalizer = new ReplicatorConfigNormalizer;

        $config = $normalizer->normalizeFieldConfig([
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => ['value' => 'blocks', 'label' => 'Blocks'],
                'mappings' => [
                    [
                        'key' => 'name',
                        'mode' => ['value' => 'field', 'label' => 'Field'],
                        'field' => ['value' => 'title', 'label' => 'Title'],
                    ],
                ],
            ],
        ]);

        $this->assertNotNull($config);
        $this->assertSame('blocks', $config['replicator_field']);
        $this->assertCount(1, $config['mappings']);
        $this->assertSame('field', $config['mappings'][0]['mode']);
        $this->assertSame('title', $config['mappings'][0]['field']);
    }
}
