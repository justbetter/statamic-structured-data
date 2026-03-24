<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataObjectBuilder;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StructuredDataObjectBuilderTest extends TestCase
{
    #[Test]
    public function pre_process_returns_default_when_data_not_array(): void
    {
        $fieldtype = new StructuredDataObjectBuilder;

        $result = $fieldtype->preProcess('not-an-array');

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('fields', $result);
        $this->assertIsArray($result['fields']);
        $this->assertEmpty($result['fields']);
    }

    #[Test]
    public function pre_process_returns_data_when_array(): void
    {
        $fieldtype = new StructuredDataObjectBuilder;

        $data = [
            'fields' => [
                ['key' => 'value'],
            ],
        ];

        $result = $fieldtype->preProcess($data);

        $this->assertEquals($data, $result);
    }

    #[Test]
    public function preload_returns_base_url(): void
    {
        Config::set('app.url', 'https://example.com');

        $fieldtype = new StructuredDataObjectBuilder;

        $result = $fieldtype->preload();

        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('base_url', $result);
        $this->assertEquals('https://example.com', $result['base_url']);
    }

    #[Test]
    public function config_field_items_returns_expected_config(): void
    {
        $fieldtype = new StructuredDataObjectBuilder;

        $reflection = new \ReflectionClass($fieldtype);
        $method = $reflection->getMethod('configFieldItems');
        $method->setAccessible(true);

        $result = $method->invoke($fieldtype);

        $this->assertIsArray($result);
        /** @var array<string, mixed> $result */
        $this->assertArrayHasKey('allow_multiple', $result);
        /** @var array<string, mixed> $allowMultiple */
        $allowMultiple = $result['allow_multiple'];
        $this->assertEquals('Allow Multiple Objects', $allowMultiple['display']);
        $this->assertEquals('toggle', $allowMultiple['type']);
        $this->assertTrue($allowMultiple['default']);
    }
}
