<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Report;

use Justbetter\StatamicStructuredData\Services\Report\CompletenessChecker;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CompletenessCheckerTest extends TestCase
{
    #[Test]
    public function it_collects_leaf_paths_including_nested_objects(): void
    {
        $checker = new CompletenessChecker;

        $schema = [
            'specialProps' => [
                'context' => 'https://schema.org',
                'type' => 'Product',
                'id' => '{{ permalink }}',
            ],
            'fields' => [
                ['key' => 'name', 'type' => 'text', 'value' => '{{ title }}'],
                [
                    'key' => 'offers',
                    'type' => 'object',
                    'value' => [
                        'specialProps' => ['type' => 'Offer'],
                        'fields' => [
                            ['key' => 'price', 'type' => 'text', 'value' => '{{ price }}'],
                        ],
                    ],
                ],
            ],
        ];

        $paths = $checker->collectExpectedPaths($schema);

        $this->assertSame(['@id', 'name', 'offers.price'], $paths);
    }

    #[Test]
    public function it_finds_empty_leaf_paths_per_schema(): void
    {
        $checker = new CompletenessChecker;

        $schemas = [[
            'specialProps' => ['type' => 'Article'],
            'fields' => [
                ['key' => 'headline', 'type' => 'text', 'value' => '{{ title }}'],
                ['key' => 'description', 'type' => 'text', 'value' => '{{ description }}'],
            ],
        ]];

        $transformed = [[
            '@type' => 'Article',
            'headline' => 'Hello',
        ]];

        $issues = $checker->findEmptyFields($schemas, $transformed);

        $this->assertCount(1, $issues);
        $this->assertSame('description', $issues[0]['field_path']);
        $this->assertSame('Article', $issues[0]['schema_type']);
    }

    #[Test]
    public function it_treats_null_empty_string_and_empty_array_as_empty(): void
    {
        $checker = new CompletenessChecker;

        $this->assertTrue($checker->isEmpty(null));
        $this->assertTrue($checker->isEmpty(''));
        $this->assertTrue($checker->isEmpty([]));
        $this->assertFalse($checker->isEmpty('value'));
        $this->assertFalse($checker->isEmpty(['a']));
        $this->assertFalse($checker->isEmpty(0));
    }

    #[Test]
    public function it_collects_paths_for_object_array_values(): void
    {
        $checker = new CompletenessChecker;

        $schema = [
            'fields' => [
                [
                    'key' => 'offers',
                    'type' => 'object_array',
                    'values' => [
                        [
                            'specialProps' => ['type' => 'Offer'],
                            'fields' => [
                                ['key' => 'price', 'type' => 'text', 'value' => '{{ price }}'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->assertSame(['offers.0.price'], $checker->collectExpectedPaths($schema));
    }

    #[Test]
    public function it_skips_invalid_schemas_fields_and_non_dynamic_ids(): void
    {
        $checker = new CompletenessChecker;

        $issues = $checker->findEmptyFields(
            ['not-an-array', [
                'specialProps' => ['type' => 123, 'id' => 456],
                'fields' => 'not-an-array',
            ]],
            [['name' => 'ok'], 'not-an-array'],
        );
        $this->assertSame([], $issues);

        $paths = $checker->collectExpectedPaths([
            'specialProps' => ['id' => 'static-id'],
            'fields' => [
                null,
                ['key' => ''],
                ['key' => 123],
                [
                    'key' => 'offers',
                    'type' => 'object_array',
                    'values' => ['not-an-array', [
                        'fields' => [
                            ['key' => 'price', 'type' => 'text', 'value' => '{{ price }}'],
                        ],
                    ]],
                ],
            ],
        ]);

        $this->assertSame(['offers.1.price'], $paths);
        $this->assertSame(['@id'], $checker->collectExpectedPaths([
            'specialProps' => ['id' => '@url'],
        ]));
    }
}
