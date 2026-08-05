<?php

namespace Justbetter\StatamicStructuredData\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Justbetter\StatamicStructuredData\Contracts\GeneratesStructuredDataReport;

class GenerateStructuredDataReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array{site: string, template_id?: string|null, triggered_by?: string|null, actor?: string|null}  $options
     */
    public function __construct(
        public array $options,
    ) {
        $this->onQueue(config()->string('justbetter.structured-data.reports.queue', 'default'));
    }

    public function handle(GeneratesStructuredDataReport $generator): void
    {
        $generator->generate($this->options);
    }
}
