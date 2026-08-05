<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Justbetter\StatamicStructuredData\Fieldtypes\AvailableVariablesFieldtype;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Fields\Field;

class AvailableVariablesFieldtypeTest extends TestCase
{
    #[Test]
    public function default_value_returns_null(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        $_ = $fieldtype->defaultValue();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function preload_returns_variables(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;

        /** @var Field $fieldMock */
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });

        $fieldtype->setField($fieldMock);

        $result = $fieldtype->preload();

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('variables', $result);
        /** @var array<string, mixed> $resultVariables */
        $resultVariables = $result['variables'];
        $this->assertArrayHasKey('site', $resultVariables);
        $this->assertArrayHasKey('globals', $resultVariables);
        $this->assertArrayHasKey('entry', $resultVariables);
        $this->assertArrayHasKey('term', $resultVariables);
    }

    #[Test]
    public function preload_includes_runway_variables_key(): void
    {
        $fieldtype = new AvailableVariablesFieldtype;
        $fieldMock = $this->mock(Field::class, function (MockInterface $mock): void {
            $mock->shouldReceive('parent')->andReturn(null);
        });
        $fieldtype->setField($fieldMock);

        $result = $fieldtype->preload();

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('variables', $result);
        /** @var array<string, mixed> $resultVariables */
        $resultVariables = $result['variables'];
        $this->assertArrayHasKey('runway', $resultVariables);
    }
}
