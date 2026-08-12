<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Tags;

use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Justbetter\StatamicStructuredData\Tags\StructuredData;
use Justbetter\StatamicStructuredData\Tests\Stubs\Product;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;

class StructuredDataTest extends TestCase
{
    #[Test]
    public function head_method_calls_action_execute_and_returns_result(): void
    {
        $expectedResult = '<script type="application/ld+json">{"@context":"https://schema.org"}</script>';

        $action = $this->mock(InjectStructuredDataAction::class, function (MockInterface $mock) use ($expectedResult): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn($expectedResult);
        });

        /** @var InjectStructuredDataAction $action */
        $tag = new StructuredData($action);

        $result = $tag->head();

        $this->assertEquals($expectedResult, $result);
    }

    #[Test]
    public function head_method_returns_null_when_action_returns_null(): void
    {
        $action = $this->mock(InjectStructuredDataAction::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->andReturn(null);
        });

        /** @var InjectStructuredDataAction $action */
        $tag = new StructuredData($action);

        $result = $tag->head();

        $this->assertNull($result);
    }

    #[Test]
    public function for_method_passes_item_and_resource_to_action(): void
    {
        $model = new Product;

        $action = $this->mock(InjectStructuredDataAction::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('executeForItem')
                ->once()
                ->with($model, 'product')
                ->andReturn('<script>for</script>');
        });

        /** @var InjectStructuredDataAction $action */
        $tag = new StructuredData($action);
        $tag->setContext([]);
        $tag->setParameters([
            'item' => $model,
            'resource' => 'product',
        ]);

        $this->assertSame('<script>for</script>', $tag->for());
    }

    #[Test]
    public function for_method_returns_null_when_item_missing(): void
    {
        $action = $this->mock(InjectStructuredDataAction::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('executeForItem');
        });

        /** @var InjectStructuredDataAction $action */
        $tag = new StructuredData($action);
        $tag->setContext([]);
        $tag->setParameters([]);

        $this->assertNull($tag->for());
    }
}
