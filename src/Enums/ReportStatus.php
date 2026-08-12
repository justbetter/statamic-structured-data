<?php

namespace Justbetter\StatamicStructuredData\Enums;

enum ReportStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}
