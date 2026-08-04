<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Justbetter\StatamicStructuredData\Actions\InjectStructuredDataAction;
use Justbetter\StatamicStructuredData\Services\StructuredDataService;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Statamic\Entries\Entry;
use Statamic\Facades\Collection as CollectionFacade;

class InjectStructuredDataActionRunwayTest extends TestCase
{
    #[Test]
    public function execute_for_item_handles_runway_model(): void
    {
        Config::set('justbetter.structured-data.runway', ['product']);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($model): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($model, false, 'product')
                ->andReturn(['<script>product</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $result = $action->executeForItem($model, 'product');

        $this->assertSame('<script>product</script>', $result);
    }

    #[Test]
    public function execute_for_item_returns_null_when_resource_not_enabled(): void
    {
        Config::set('justbetter.structured-data.runway', []);

        $model = new class extends Model
        {
            protected $table = 'products';
        };

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('getJsonLdScripts');
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertNull($action->executeForItem($model, 'product'));
    }

    #[Test]
    public function execute_for_item_handles_entry(): void
    {
        Config::set('justbetter.structured-data.collections', ['blog']);

        $collection = CollectionFacade::make('blog');
        $collection->save();
        $entry = (new Entry)->collection($collection)->id('entry-123');

        $service = $this->mock(StructuredDataService::class, function (MockInterface $mock) use ($entry): void {
            $mock->shouldReceive('getJsonLdScripts')
                ->once()
                ->with($entry)
                ->andReturn(['<script>entry</script>']);
        });

        /** @var StructuredDataService $service */
        $action = new InjectStructuredDataAction($service);

        $this->assertSame('<script>entry</script>', $action->executeForItem($entry));
    }
}
