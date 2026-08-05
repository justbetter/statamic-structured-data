<?php

namespace Justbetter\StatamicStructuredData\Tests\Unit\Commands;

use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Testing\PendingCommand;
use Justbetter\StatamicStructuredData\Commands\StructuredDataReportCommand;
use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;
use Justbetter\StatamicStructuredData\Data\Report;
use Justbetter\StatamicStructuredData\Data\ReportItem;
use Justbetter\StatamicStructuredData\Enums\ReportIssueType;
use Justbetter\StatamicStructuredData\Enums\ReportItemType;
use Justbetter\StatamicStructuredData\Enums\ReportStatus;
use Justbetter\StatamicStructuredData\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Statamic\Entries\Entry;
use Statamic\Events\EntryCreated;
use Statamic\Facades\Collection as CollectionFacade;
use Statamic\Facades\Entry as EntryFacade;
use Statamic\Facades\Site as SiteFacade;
use Statamic\Sites\Site;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class StructuredDataReportCommandTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/structured-data-reports-cli-'.uniqid());
        File::deleteDirectory($this->path);

        config()->set('justbetter.structured-data.reports.driver', 'file');
        config()->set('justbetter.structured-data.reports.path', $this->path);
        config()->set('justbetter.structured-data.collections', ['pages']);
        config()->set('justbetter.structured-data.taxonomies', []);
        config()->set('justbetter.structured-data.runway', []);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    #[Test]
    public function it_generates_a_report_via_cli(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $entry */
        $entry = EntryFacade::make();
        $entry
            ->collection('pages')
            ->locale($site)
            ->slug('home')
            ->data(['title' => 'Home'])
            ->published(true)
            ->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
            '--json' => true,
        ]);
        $command
            ->expectsOutputToContain('"status": "'.ReportStatus::Completed->value.'"')
            ->assertSuccessful();
    }

    #[Test]
    public function it_uses_selected_site_when_site_option_is_empty(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => '',
            '--json' => true,
        ]);
        $command
            ->expectsOutputToContain('"site": "'.$site.'"')
            ->assertSuccessful();
    }

    #[Test]
    public function it_renders_a_table_with_no_issues(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
        ]);
        $command
            ->expectsOutputToContain('No issues found.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_renders_a_table_with_issues(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('template')
            ->data([
                'title' => 'Template',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => ['type' => 'WebPage'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => '{{ title }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Entry $entry */
        $entry = EntryFacade::make();
        $entry
            ->collection('pages')
            ->locale($site)
            ->slug('home')
            ->data(['title' => 'Home'])
            ->published(true)
            ->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
        ]);
        $command
            ->expectsOutputToContain('Errors:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_queues_report_generation(): void
    {
        Bus::fake();

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
            '--queue' => true,
        ]);
        $command
            ->expectsOutputToContain('Structured data report queued for site ['.$site.'].')
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_generation_throws(): void
    {
        $generator = $this->mock(GeneratesStructuredDataReport::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andThrow(new RuntimeException('boom'));

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
        ]);
        $command
            ->expectsOutputToContain('Report generation failed: boom')
            ->assertFailed();
    }

    #[Test]
    public function it_fails_when_fail_on_issues_is_set_and_issues_exist(): void
    {
        Event::fake([EntryCreated::class]);

        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var Entry $template */
        $template = EntryFacade::make();
        $template
            ->collection('structured_data_templates')
            ->locale($site)
            ->slug('template')
            ->data([
                'title' => 'Template',
                'blueprint_type' => 'collection',
                'use_for_collection' => 'pages',
                'apply_automatically' => true,
                'schema_data' => [[
                    'specialProps' => ['type' => 'WebPage'],
                    'fields' => [
                        ['key' => 'name', 'type' => 'text', 'value' => '{{ title }}'],
                    ],
                ]],
            ])
            ->published(true)
            ->save();

        /** @var Entry $entry */
        $entry = EntryFacade::make();
        $entry
            ->collection('pages')
            ->locale($site)
            ->slug('home')
            ->data(['title' => 'Home'])
            ->published(true)
            ->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
            '--fail-on-issues' => true,
        ]);
        $command->assertFailed();
    }

    #[Test]
    public function it_outputs_json(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();
        $site = $defaultSite->handle();

        CollectionFacade::make('pages')->sites([$site])->save();
        CollectionFacade::make('structured_data_templates')->sites([$site])->save();

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--site' => $site,
            '--json' => true,
        ]);
        $command
            ->expectsOutputToContain('"status": "'.ReportStatus::Completed->value.'"')
            ->assertSuccessful();
    }

    #[Test]
    public function it_fails_when_fail_on_warnings_is_set_and_warnings_exist(): void
    {
        $generator = $this->mock(GeneratesStructuredDataReport::class);
        $generator->shouldReceive('generate')
            ->once()
            ->andReturn(Report::make([
                'id' => 'report-1',
                'site' => 'default',
                'status' => ReportStatus::Completed->value,
                'error_count' => 0,
                'warning_count' => 2,
                'items' => [],
            ]));

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--json' => true,
            '--fail-on-warnings' => true,
        ]);
        $command->assertFailed();
    }

    #[Test]
    public function it_converts_mixed_values_for_cli_helpers(): void
    {
        $command = app(StructuredDataReportCommand::class);

        $stringFromMixed = new ReflectionMethod($command, 'stringFromMixed');
        $intFromMixed = new ReflectionMethod($command, 'intFromMixed');

        $this->assertSame('12', $stringFromMixed->invoke($command, 12));
        $this->assertSame('', $stringFromMixed->invoke($command, ['nope']));
        $this->assertSame(3, $intFromMixed->invoke($command, 3.9));
        $this->assertSame(4, $intFromMixed->invoke($command, '4'));
        $this->assertSame(0, $intFromMixed->invoke($command, ['nope']));
    }

    #[Test]
    public function it_passes_template_id_option_to_generator(): void
    {
        $generator = $this->mock(GeneratesStructuredDataReport::class);
        $generator->shouldReceive('generate')
            ->once()
            ->withArgs(fn (array $options): bool => ($options['template_id'] ?? null) === 'template-abc')
            ->andReturn(Report::make([
                'id' => 'report-1',
                'site' => 'default',
                'status' => ReportStatus::Completed->value,
                'items' => [],
            ]));

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--template' => 'template-abc',
            '--json' => true,
        ]);
        $command->assertSuccessful();
    }

    #[Test]
    public function it_falls_back_to_default_site_when_selected_handle_is_empty(): void
    {
        /** @var Site $defaultSite */
        $defaultSite = SiteFacade::default();

        $emptySite = $this->mock(Site::class);
        $emptySite->shouldReceive('handle')->andReturn('');

        SiteFacade::shouldReceive('selected')->andReturn($emptySite);
        SiteFacade::shouldReceive('default')->andReturn($defaultSite);

        $generator = $this->mock(GeneratesStructuredDataReport::class);
        $generator->shouldReceive('generate')
            ->once()
            ->withArgs(fn (array $options): bool => $options['site'] === $defaultSite->handle())
            ->andReturn(Report::make([
                'id' => 'report-1',
                'site' => $defaultSite->handle(),
                'status' => ReportStatus::Completed->value,
                'items' => [],
            ]));

        /** @var PendingCommand $command */
        $command = $this->artisan('structured-data:report', [
            '--json' => true,
        ]);
        $command->assertSuccessful();
    }

    #[Test]
    public function it_renders_issue_rows_from_report_items(): void
    {
        $command = app(StructuredDataReportCommand::class);
        $buffer = new BufferedOutput;
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));

        $report = Report::make([
            'id' => 'report-1',
            'site' => 'default',
            'status' => ReportStatus::Completed->value,
            'items_scanned' => 1,
            'missing_automatic_template_count' => 1,
            'incomplete_field_count' => 0,
            'items' => [
                ReportItem::make([
                    'id' => 'item-1',
                    'issue_type' => ReportIssueType::MissingAutomaticTemplate->value,
                    'severity' => 'error',
                    'item_type' => ReportItemType::Entry->value,
                    'item_id' => 'entry-1',
                    'item_title' => 'Home',
                    'template_id' => 'template-1',
                    'template_title' => 'Template',
                    'field_path' => null,
                    'scope_handle' => 'pages',
                    'scope_type' => 'collection',
                ]),
            ],
        ]);

        $method = new ReflectionMethod($command, 'renderTable');
        $method->invoke($command, $report);

        $output = $buffer->fetch();
        $this->assertStringContainsString('report-1', $output);
        $this->assertStringContainsString('Home', $output);
        $this->assertStringContainsString(ReportIssueType::MissingAutomaticTemplate->value, $output);
    }
}
