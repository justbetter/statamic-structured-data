<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Fieldtypes;

use Justbetter\StatamicStructuredData\Fieldtypes\StructuredDataPreview;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Fields\Field;
use Statamic\Taxonomies\LocalizedTerm;

class StructuredDataPreviewTest extends TestCase
{
    #[Test]
    public function default_value_returns_null(): void
    {
        $fieldtype = new StructuredDataPreview;

        $_ = $fieldtype->defaultValue();

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function preload_includes_schema_validator_url(): void
    {
        $fieldtype = new StructuredDataPreview;
        $field = new Field('structured_data_preview', [
            'type' => 'structured_data_preview',
        ]);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertSame('https://validator.schema.org/', $preload['schema_validator_url']);
        $this->assertNull($preload['item_url']);
    }

    #[Test]
    public function preload_resolves_item_url_from_entry_parent(): void
    {
        $collection = CollectionFacade::make('pages')->routes('{slug}')->save();
        $entry = (new Entry)
            ->collection($collection)
            ->slug('about')
            ->id('entry-about');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('absoluteUrl')->andReturn('https://example.com/about');

        $fieldtype = new StructuredDataPreview;
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('parent')->andReturn($entryMock);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertSame('https://example.com/about', $preload['item_url']);
        $this->assertSame('https://validator.schema.org/', $preload['schema_validator_url']);
    }

    #[Test]
    public function preload_resolves_item_url_from_term_parent(): void
    {
        $term = $this->mock(LocalizedTerm::class, function ($mock): void {
            $mock->shouldReceive('absoluteUrl')->andReturn('https://example.com/categories/news');
        });

        $fieldtype = new StructuredDataPreview;
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('parent')->andReturn($term);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertSame('https://example.com/categories/news', $preload['item_url']);
    }

    #[Test]
    public function preload_returns_null_item_url_when_absolute_url_fails(): void
    {
        $collection = CollectionFacade::make('pages')->routes('{slug}')->save();
        $entry = (new Entry)
            ->collection($collection)
            ->slug('broken')
            ->id('entry-broken');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('absoluteUrl')->andThrow(new \RuntimeException('no url'));

        $fieldtype = new StructuredDataPreview;
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('parent')->andReturn($entryMock);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertNull($preload['item_url']);
    }

    #[Test]
    public function preload_returns_null_item_url_when_absolute_url_is_empty(): void
    {
        $collection = CollectionFacade::make('pages')->routes('{slug}')->save();
        $entry = (new Entry)
            ->collection($collection)
            ->slug('empty-url')
            ->id('entry-empty-url');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('absoluteUrl')->andReturn('');

        $fieldtype = new StructuredDataPreview;
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('parent')->andReturn($entryMock);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertNull($preload['item_url']);
    }

    #[Test]
    public function preload_returns_null_item_url_when_absolute_url_is_not_a_string(): void
    {
        $collection = CollectionFacade::make('pages')->routes('{slug}')->save();
        $entry = (new Entry)
            ->collection($collection)
            ->slug('non-string-url')
            ->id('entry-non-string-url');

        $entryMock = Mockery::mock($entry)->makePartial();
        $entryMock->shouldReceive('absoluteUrl')->andReturn(null);

        $fieldtype = new StructuredDataPreview;
        $field = Mockery::mock(Field::class);
        $field->shouldReceive('parent')->andReturn($entryMock);
        $fieldtype->setField($field);

        $preload = $fieldtype->preload();

        $this->assertNull($preload['item_url']);
    }
}
