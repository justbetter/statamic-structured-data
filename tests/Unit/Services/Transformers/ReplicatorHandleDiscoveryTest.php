<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\PreviewContext;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorObjectArrayTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorRowNormalizer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ReplicatorHandleDiscoveryTest extends TestCase
{
    #[Test]
    public function it_infers_replicator_handle_when_configured_handle_is_empty(): void
    {
        $transformer = new ReplicatorObjectArrayTransformer(
            new ReplicatorRowNormalizer,
            new PreviewContext
        );

        $item = new class
        {
            public function blueprint(): object
            {
                return new class
                {
                    public function fields(): object
                    {
                        return new class
                        {
                            public function all(): array
                            {
                                return [
                                    new class
                                    {
                                        public function type(): string
                                        {
                                            return 'replicator';
                                        }

                                        public function handle(): string
                                        {
                                            return 'content_blocks';
                                        }
                                    },
                                ];
                            }
                        };
                    }

                    public function hasField(string $handle): bool
                    {
                        return $handle === 'content_blocks';
                    }
                };
            }

            public function data(): object
            {
                return new class
                {
                    public function get(string $key): mixed
                    {
                        return [
                            ['type' => 'item', 'title' => 'test 1'],
                            ['type' => 'item', 'title' => 'test 1'],
                        ];
                    }
                };
            }

            public function get(string $key): mixed
            {
                return $this->data()->get($key);
            }
        };

        $field = [
            'type' => 'replicator_object_array',
            'config' => [
                'replicator_field' => '',
                'mappings' => [
                    ['key' => 'test23', 'mode' => 'field', 'field' => 'title'],
                ],
            ],
        ];

        $result = $transformer->transform($field, $item);

        $this->assertCount(2, $result);
        $this->assertSame('test 1', $result[0]['test23']);
        $this->assertSame('test 1', $result[1]['test23']);
    }
}
