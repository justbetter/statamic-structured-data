<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Tags;

use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Justbetter\StatamicStructuredData\Tags\StructuredData;
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

        /** @var \Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction $action */
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

        /** @var \Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction $action */
        $tag = new StructuredData($action);

        $result = $tag->head();

        $this->assertNull($result);
    }
}
