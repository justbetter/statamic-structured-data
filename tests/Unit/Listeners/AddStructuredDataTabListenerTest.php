<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Listeners;

use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Listeners\AddStructuredDataTabListener;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Events\EntryBlueprintFound;
use Statamic\Events\TermBlueprintFound;
use Statamic\Fields\Blueprint;

class AddStructuredDataTabListenerTest extends TestCase
{
    #[Test]
    public function handle_adds_tab_for_enabled_collection(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('collections.blog');
            $mock->shouldReceive('contents')->andReturn(['tabs' => []]);
            $mock->shouldReceive('setContents')->once()->with(\Mockery::on(function ($contents) {
                return isset($contents['tabs']['structured_data']);
            }));
        });

        $event = new EntryBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }

    #[Test]
    public function handle_does_not_add_tab_for_disabled_collection(): void
    {
        Config::set('justbetter.structured-data.collections', ['other_collection']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('collections.blog');
            $mock->shouldReceive('setContents')->never();
        });

        $event = new EntryBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }

    #[Test]
    public function handle_adds_tab_for_enabled_taxonomy(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('taxonomies.categories');
            $mock->shouldReceive('contents')->andReturn(['tabs' => []]);
            $mock->shouldReceive('setContents')->once()->with(\Mockery::on(function ($contents) {
                return isset($contents['tabs']['structured_data']);
            }));
        });

        $event = new TermBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }

    #[Test]
    public function handle_does_not_add_tab_for_disabled_taxonomy(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['other_taxonomy']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('taxonomies.categories');
            $mock->shouldReceive('setContents')->never();
        });

        $event = new TermBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }

    #[Test]
    public function handle_collection_blueprint_found_adds_tab(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('collections.blog');
            $mock->shouldReceive('contents')->andReturn(['tabs' => []]);
            $mock->shouldReceive('setContents')->once()->with(\Mockery::on(function ($contents) {
                return isset($contents['tabs']['structured_data']);
            }));
        });

        /** @var Blueprint $blueprint */
        $listener = new AddStructuredDataTabListener;
        $listener->handleCollectionBlueprintFound($blueprint);

    }

    #[Test]
    public function handle_taxonomy_blueprint_found_adds_tab(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('taxonomies.categories');
            $mock->shouldReceive('contents')->andReturn(['tabs' => []]);
            $mock->shouldReceive('setContents')->once()->with(\Mockery::on(function ($contents) {
                return isset($contents['tabs']['structured_data']);
            }));
        });

        /** @var Blueprint $blueprint */
        $listener = new AddStructuredDataTabListener;
        $listener->handleTaxonomyBlueprintFound($blueprint);

    }

    #[Test]
    public function handle_collection_blueprint_found_does_not_add_tab_when_already_exists(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('collections.blog');
            $mock->shouldReceive('contents')->andReturn(['tabs' => ['structured_data' => []]]);
            $mock->shouldReceive('setContents')->never();
        });

        /** @var Blueprint $blueprint */
        $listener = new AddStructuredDataTabListener;
        $listener->handleCollectionBlueprintFound($blueprint);

    }

    #[Test]
    public function handle_taxonomy_blueprint_found_does_not_add_tab_when_already_exists(): void
    {
        Config::set('justbetter.structured-data.taxonomies', ['categories']);

        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('taxonomies.categories');
            $mock->shouldReceive('contents')->andReturn(['tabs' => ['structured_data' => []]]);
            $mock->shouldReceive('setContents')->never();
        });

        /** @var Blueprint $blueprint */
        $listener = new AddStructuredDataTabListener;
        $listener->handleTaxonomyBlueprintFound($blueprint);

    }

    #[Test]
    public function handle_does_nothing_for_non_collection_namespace(): void
    {
        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('other.namespace');
            $mock->shouldReceive('setContents')->never();
        });

        $event = new EntryBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }

    #[Test]
    public function handle_does_nothing_for_non_taxonomy_namespace(): void
    {
        $blueprint = $this->mock(Blueprint::class, function (MockInterface $mock): void {
            $mock->shouldReceive('namespace')->andReturn('other.namespace');
            $mock->shouldReceive('setContents')->never();
        });

        $event = new TermBlueprintFound($blueprint);

        $listener = new AddStructuredDataTabListener;
        $listener->handle($event);

    }
}
