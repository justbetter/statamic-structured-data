<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\PreviewContext;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorObjectArrayTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReplicatorJsonPreviewTest extends TestCase
{
    #[Test]
    public function it_transforms_replicator_data_from_json_string_publish_values(): void
    {
        $previewContext = new PreviewContext;
        $previewContext->setValues([
            'blocks' => json_encode([
                [
                    'type' => 'test_set',
                    'title' => 'Hello World',
                ],
            ]),
        ]);

        $transformer = new ReplicatorObjectArrayTransformer(
            new ReplicatorRowNormalizer,
            $previewContext
        );

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'blocks',
                'mappings' => [
                    ['key' => 'test2', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field);

        $this->assertCount(1, $result);
        $this->assertSame('Hello World', $result[0]['test2']);
    }

    #[Test]
    public function it_falls_back_to_entry_data_when_preview_values_are_empty_array(): void
    {
        $previewContext = new PreviewContext;
        $previewContext->setValues([
            'blocks' => [],
        ]);

        $transformer = new ReplicatorObjectArrayTransformer(
            new ReplicatorRowNormalizer,
            $previewContext
        );

        $item = new class
        {
            public function get(string $key): mixed
            {
                return [
                    ['type' => 'test_set', 'title' => 'From Entry'],
                ];
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => 'blocks',
                'mappings' => [
                    ['key' => 'name', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(1, $result);
        $this->assertSame('From Entry', $result[0]['name']);
    }
}
