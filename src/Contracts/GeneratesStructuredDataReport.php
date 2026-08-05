<?php

namespace Justbetter\StatamicStructuredData\Contracts;

use Justbetter\StatamicStructuredData\Data\Report;

interface GeneratesStructuredDataReport
{
    /**
     * @param  array{site: string, template_id?: string|null, triggered_by?: string|null, actor?: string|null}  $options
     */
    public function generate(array $options): Report;
}
