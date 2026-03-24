<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Services\Transformers;

use Justbetter\StatamicStructuredData\Services\Transformers\DefaultFieldTransformer;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerFactory;
use Justbetter\StatamicStructuredData\Services\Transformers\FieldTransformerInterface;
use Justbetter\StatamicStructuredData\Services\Transformers\ReplicatorObjectArrayTransformer;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FieldTransformerFactoryTest extends TestCase
{
    #[Test]
    public function it_returns_replicator_object_array_transformer_for_replicator_object_array_type(): void
    {
        $factory = new FieldTransformerFactory;

        $transformer = $factory->getTransformer('replicator_object_array');

        $this->assertInstanceOf(ReplicatorObjectArrayTransformer::class, $transformer);
        $this->assertInstanceOf(FieldTransformerInterface::class, $transformer);
    }

    #[Test]
    public function it_returns_default_field_transformer_for_null_type(): void
    {
        $factory = new FieldTransformerFactory;

        $transformer = $factory->getTransformer(null);

        $this->assertInstanceOf(DefaultFieldTransformer::class, $transformer);
        $this->assertInstanceOf(FieldTransformerInterface::class, $transformer);
    }

    #[Test]
    public function it_returns_default_field_transformer_for_unknown_type(): void
    {
        $factory = new FieldTransformerFactory;

        $transformer = $factory->getTransformer('unknown_type');

        $this->assertInstanceOf(DefaultFieldTransformer::class, $transformer);
        $this->assertInstanceOf(FieldTransformerInterface::class, $transformer);
    }
}
